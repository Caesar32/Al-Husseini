<?php

/*
 * Measures the real barcode label layout in headless Chrome: for every size, font and product scenario
 * it reports text that overflows, is clipped or overlaps, and whether the page warned before printing.
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
    within('company', textBox(label.querySelector('.label__company')));
    within('code', textBox(label.querySelector('.label__code')));
    within('barcode', label.querySelector('img').getBoundingClientRect());

    const footer = label.querySelector('.label__footer');
    if (footer) {
        const parts = [...footer.children].filter((s) => s.textContent.trim() !== '').map(textBox);
        parts.forEach((p) => within('footer', p));
        if (parts.length === 2 && parts[0].left < parts[1].right && parts[1].left < parts[0].right) problems.push('warranty and price overlap');
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

$failures = 0;
$total = 0;
foreach (BarcodeLabelSize::cases() as $size) {
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
                PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null',
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
