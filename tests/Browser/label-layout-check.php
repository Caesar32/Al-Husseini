<?php

/*
 * Measures the real barcode label layout in headless Chrome: for every size, font and product scenario
 * it reports text that overflows, is clipped or overlaps, and whether the page warned before printing;
 * for every size it also prints a PDF and checks the page dimensions and that no blank page appears.
 * overflow:hidden hides these problems from any HTML or CSS assertion, so only a browser's layout can
 * find them.
 *
 * Not a Pest test (it needs Chrome and writes nothing to the database). Run it after changing the label:
 *     php tests/Browser/label-layout-check.php [path-to-chrome]
 * Exit code 0 = no problem, 1 = a problem was found, 2 = the check itself could not run.
 *
 * Status per label: OK = fits and auto-prints; WARNED = exceeds a limit, and the page says so and does
 * not print by itself; FAIL = anything else.
 */

use App\Contracts\Catalog\BarcodeLabelServiceInterface;
use App\Enums\BarcodeLabelSize;
use App\Models\Product;
use Illuminate\Contracts\Console\Kernel;

$root = dirname(__DIR__, 2);
require "{$root}/vendor/autoload.php";
$app = require "{$root}/bootstrap/app.php";
$app->make(Kernel::class)->bootstrap();

$chrome = $argv[1] ?? getenv('CHROME_BIN') ?: 'C:/Program Files/Google/Chrome/Application/chrome.exe';
if (! is_file($chrome)) {
    fwrite(STDERR, "Chrome not found at {$chrome}. Pass its path as the first argument.\n");
    exit(2);
}

/** Names below are the seeded catalog's; the limits are StoreProductRequest's maximums. */
$scenarios = [
    'real battery' => ['name' => 'بطارية كلورايد 70 أمبير جولد (Chloride Gold 70Ah)', 'capacity_ah' => '70', 'barcode' => '6221234567890', 'sku' => 'BAT-CHL-70G', 'retail_price' => 3850, 'warranty_months' => 12],
    'real oil' => ['name' => 'زيت شل هيلكس الترا 5W-40 تخليقي بالكامل 4 لتر', 'barcode' => '6221234567891', 'sku' => 'OIL-SHL-5W40-4L', 'retail_price' => 950.5, 'warranty_months' => 0],
    'largest footer' => ['name' => 'بطارية', 'sku' => 'BAT-1', 'retail_price' => 9999999.5, 'warranty_months' => 120],
    'longest sku' => ['name' => 'بطارية', 'sku' => str_repeat('A', 50), 'retail_price' => 100, 'warranty_months' => 12],
    'longest name' => ['name' => str_repeat('بطارية كلورايد ', 14), 'sku' => 'BAT-1', 'retail_price' => 3850, 'warranty_months' => 12],
];

$printStub = '<script>window.print = () => { window.printCalled = true; };</script>';

/** Runs inside the page after labels-print.js. Sub-pixel tolerance: 0.5 CSS px is about 0.13 mm. */
$probe = <<<'JS'
<script>
window.addEventListener('load', async () => {
    await document.fonts.ready;
    await new Promise((resolve) => setTimeout(resolve, 200));

    const textBox = (el) => { const r = document.createRange(); r.selectNodeContents(el); return r.getBoundingClientRect(); };
    const label = document.querySelector('.label');
    const problems = [];
    const box = label.getBoundingClientRect();
    const style = getComputedStyle(label);
    const inner = { left: box.left + parseFloat(style.paddingLeft), right: box.right - parseFloat(style.paddingRight), top: box.top + parseFloat(style.paddingTop), bottom: box.bottom - parseFloat(style.paddingBottom) };
    const within = (name, rect) => {
        if (rect.left < inner.left - 0.5 || rect.right > inner.right + 0.5) problems.push(name + ' overflows the width');
    };
    const barcode = label.querySelector('img').getBoundingClientRect();
    within('barcode', barcode);

    const centre = (rect) => (rect.left + rect.right) / 2;
    const labelCentre = centre(box);
    if (Math.abs(centre(barcode) - labelCentre) > 0.5) problems.push('barcode is off-centre');
    if (style.paddingTop !== style.paddingBottom || parseFloat(style.paddingTop) < 3.77) problems.push('top or bottom margin under 1 mm');
    if (parseFloat(style.paddingLeft) < 7.55 || parseFloat(style.paddingRight) < 7.55) problems.push('side margin under 2 mm');

    const overlap = (a, b) => a.left < b.right && b.left < a.right && a.top < b.bottom && b.top < a.bottom;
    // Overlap and order use element boxes: a font's text box is taller than its line, so it reaches into the next row.
    const elementBox = (el) => el.getBoundingClientRect();
    const company = label.querySelector('.label__company');
    const code = label.querySelector('.label__code');
    within('company', textBox(company));
    within('code', textBox(code));
    if (elementBox(code).top < barcode.bottom - 0.5) problems.push('code is not under the barcode');

    const price = label.querySelector('.label__price');
    if (price) {
        within('price', textBox(price));
        if (overlap(elementBox(company), elementBox(price))) problems.push('company and price overlap');
    }
    const warranty = label.querySelector('.label__warranty');
    if (warranty) {
        within('warranty', textBox(warranty));
        if (overlap(elementBox(code), elementBox(warranty))) problems.push('warranty and code overlap');
    }

    // Lines without the clamp; scrollHeight would not do, since a font's box can exceed its line-height.
    const title = label.querySelector('.label__title');
    title.style.display = 'block';
    title.style.webkitLineClamp = 'none';
    title.style.flex = 'none';
    const lines = Math.round(title.getBoundingClientRect().height / parseFloat(getComputedStyle(title).lineHeight));
    title.style.display = '';
    title.style.webkitLineClamp = '';
    title.style.flex = '';
    const titleCut = lines > 2;

    // Natural row heights: flex would otherwise squeeze a row with overflow:hidden and clip it silently.
    const rows = [...label.children];
    rows.forEach((row) => { row.style.flex = 'none'; });
    const needed = rows.reduce((sum, row) => sum + row.getBoundingClientRect().height, 0);
    rows.forEach((row) => { row.style.flex = ''; });
    if (needed > inner.bottom - inner.top + 0.5) problems.push('content is taller than the label');

    const warned = [...document.querySelectorAll('.notice--warning')].some((n) => !n.hidden);
    const dense = label.classList.contains('label--dense');
    const shouldWarn = titleCut || dense;
    if (shouldWarn && !warned) problems.push('no warning shown');
    if (!shouldWarn && warned) problems.push('false warning');
    if (shouldWarn && window.printCalled) problems.push('printed despite the warning');
    if (!shouldWarn && !window.printCalled) problems.push('print dialog did not open');

    const cairo = [...document.fonts].some((f) => f.family.includes('Cairo') && f.status === 'loaded');
    const reasons = [titleCut ? `name needs ${lines} lines, 2 fit` : '', dense ? 'barcode too dense' : ''].filter(Boolean);
    document.body.innerHTML = '<pre id="result">' + JSON.stringify({
        cairo, problems: [...new Set(problems)], reasons, spareMm: +((inner.bottom - inner.top - needed) / 3.7795).toFixed(2),
    }) + '</pre>';
});
</script>
JS;

$service = $app->make(BarcodeLabelServiceInterface::class);
$publicUrl = 'file:///'.str_replace('\\', '/', "{$root}/public");
$workDir = str_replace('\\', '/', sys_get_temp_dir()).'/label-layout-check-'.getmypid();
mkdir($workDir);

$nullDevice = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';

$failures = 0;
$total = 0;
foreach (BarcodeLabelSize::cases() as $size) {
    // The page Chrome really prints: an invalid @page rule falls back to a full sheet, and a spill feeds a blank label.
    $copies = 3;
    $labels = $service->forProduct(new Product($scenarios['real battery']), $copies, $size, true);
    $html = view('admin.labels.print', ['labels' => $labels, 'size' => $size, 'title' => 'layout check'])->render();
    $file = "{$workDir}/pages.html";
    file_put_contents($file, str_replace(rtrim(asset(''), '/'), $publicUrl, str_replace('</title>', '</title>'.$printStub, $html)));
    shell_exec(sprintf(
        '"%s" --headless=new --disable-gpu --no-pdf-header-footer --allow-file-access-from-files --user-data-dir=%s --virtual-time-budget=10000 --print-to-pdf=%s %s 2>%s',
        $chrome,
        escapeshellarg("{$workDir}/profile"),
        escapeshellarg("{$workDir}/pages.pdf"),
        escapeshellarg("file:///{$file}"),
        $nullDevice,
    ));
    if (preg_match_all('/MediaBox\s*\[\s*0 0 ([\d.]+) ([\d.]+)\s*\]/', (string) @file_get_contents("{$workDir}/pages.pdf"), $boxes) === 0) {
        fwrite(STDERR, "Chrome produced no PDF for {$size->value}.\n");
        exit(2);
    }
    $pageWidth = round((float) $boxes[1][0] * 25.4 / 72, 1);
    $pageHeight = round((float) $boxes[2][0] * 25.4 / 72, 1);
    $pageFailed = count($boxes[1]) !== $copies
        || abs($pageWidth - $size->widthMm()) > 0.5
        || abs($pageHeight - $size->heightMm()) > 0.5;
    $total++;
    $failures += $pageFailed ? 1 : 0;
    printf("\n%-6s %s PDF: %d labels = %d pages of %.1f x %.1f mm\n", $pageFailed ? 'FAIL' : 'OK', $size->value, $copies, count($boxes[1]), $pageWidth, $pageHeight);

    foreach (['Cairo' => true, 'offline fallback' => false] as $fontName => $useWebFont) {
        echo "\n{$size->value} / {$fontName}\n";

        foreach ($scenarios as $scenario => $attributes) {
            $labels = $service->forProduct(new Product($attributes), 1, $size, true);
            $html = view('admin.labels.print', ['labels' => $labels, 'size' => $size, 'title' => 'layout check'])->render();
            $html = str_replace(rtrim(asset(''), '/'), $publicUrl, $html);
            $html = str_replace('</title>', '</title>'.$printStub, $html);
            if (! $useWebFont) {
                $html = preg_replace('#<link href="https://fonts[^>]*>#', '', $html);
            }
            $html = str_replace('</body>', $probe.'</body>', $html);

            $file = "{$workDir}/page.html";
            file_put_contents($file, $html);

            $dom = (string) shell_exec(sprintf(
                '"%s" --headless=new --disable-gpu --allow-file-access-from-files --user-data-dir=%s --virtual-time-budget=10000 --dump-dom %s 2>%s',
                $chrome,
                escapeshellarg("{$workDir}/profile"),
                escapeshellarg("file:///{$file}"),
                $nullDevice,
            ));

            if (! preg_match('#<pre id="result">(.*?)</pre>#s', $dom, $match)) {
                fwrite(STDERR, "Chrome returned no measurement for {$size->value} / {$fontName} / {$scenario}.\n");
                exit(2);
            }

            $result = json_decode(html_entity_decode($match[1]), true);
            if ($useWebFont && ! $result['cairo']) {
                fwrite(STDERR, "Cairo did not load (no internet?): this run measured the fallback font, so it proves nothing about Cairo.\n");
                exit(2);
            }

            $total++;
            $failed = $result['problems'] !== [];
            $failures += $failed ? 1 : 0;
            printf(
                "  %-6s %-15s spare %5.2f mm  %s\n",
                $failed ? 'FAIL' : ($result['reasons'] === [] ? 'OK' : 'WARNED'),
                $scenario,
                $result['spareMm'],
                implode(', ', [...$result['problems'], ...$result['reasons']]),
            );
        }
    }
}

echo $failures === 0 ? "\n{$total} labels measured, no problems.\n" : "\n{$failures} of {$total} labels have problems.\n";
exit($failures === 0 ? 0 : 1);
