<?php

use App\Enums\BarcodeLabelSize;
use App\Enums\ProductUnit;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\InitialDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function labelProduct(array $overrides = []): Product
{
    static $sequence = 0;
    $sequence++;

    return Product::create(array_merge([
        'category_id' => Category::firstOrCreate(['slug' => 'label-tests'], ['name' => 'اختبار ملصقات'])->id,
        'sku' => "LBL-{$sequence}",
        'name' => "بطارية اختبار {$sequence}",
        'brand' => 'اختبار',
        'unit' => ProductUnit::Piece->value,
        'cost_price' => 900,
        'retail_price' => 1250,
        'current_stock' => 0,
        'warranty_months' => 12,
        'is_battery' => true,
        'is_active' => true,
    ], $overrides));
}

function labelPurchase(int $branchId, string $number = 'LBL-INV-1'): PurchaseInvoice
{
    $supplier = Supplier::firstOrCreate(['phone' => '01055550001'], ['name' => 'مورد', 'company_name' => 'شركة مورد']);

    return PurchaseInvoice::create([
        'invoice_number' => $number,
        'supplier_id' => $supplier->id,
        'branch_id' => $branchId,
        'received_by' => User::firstOrFail()->id,
        'invoice_date' => '2026-11-05',
        'subtotal' => 0,
        'final_amount' => 0,
    ]);
}

function labelLine(PurchaseInvoice $purchase, Product $product, int|float $quantity): PurchaseInvoiceItem
{
    return PurchaseInvoiceItem::create([
        'purchase_invoice_id' => $purchase->id,
        'product_id' => $product->id,
        'quantity' => $quantity,
        'unit_cost_price' => 10,
        'total_cost_price' => 10,
    ]);
}

function labelCount(string $html): int
{
    return substr_count($html, '<section class="label');
}

/** @return list<string> The SVG documents of the barcode images on the page. */
function labelSvgs(string $html): array
{
    preg_match_all('#data:image/svg\+xml;base64,([A-Za-z0-9+/=]+)#', $html, $matches);

    return array_map('base64_decode', $matches[1]);
}

/**
 * The first label on the page, read from its elements instead of searching the whole page's text.
 *
 * @return array{rows: list<string>, company: string, price: ?string, title: string, code: string, warranty: ?string, footer: list<string>, alt: string, image: string, element_after_barcode: string, text: string}
 */
function parsedLabel(string $html): array
{
    $dom = new DOMDocument();
    $previous = libxml_use_internal_errors(true); // libxml does not know HTML5 elements such as <main>.
    $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    $xpath = new DOMXPath($dom);
    $label = $xpath->query('//section[contains(@class, "label")]')->item(0);
    $part = fn (string $class) => $xpath->query(".//*[@class='label__{$class}']", $label)->item(0);
    $text = fn (DOMNode $node) => trim(preg_replace('/\s+/u', ' ', $node->textContent));
    $elements = fn (DOMNode $node) => array_values(array_filter(iterator_to_array($node->childNodes), fn ($n) => $n instanceof DOMElement));

    $image = $xpath->query('.//img', $label);
    expect($image->length)->toBe(1);
    $optional = fn (string $class) => ($node = $part($class)) ? $text($node) : null;

    return [
        'rows' => array_map(fn (DOMElement $row) => $row->getAttribute('class'), $elements($label)),
        'company' => $text($part('company')),
        'price' => $optional('price'),
        'title' => $text($part('title')),
        'code' => $text($part('code')),
        'warranty' => $optional('warranty'),
        'footer' => array_map(fn (DOMElement $child) => $child->getAttribute('class'), $elements($part('footer'))),
        'alt' => $image->item(0)->getAttribute('alt'),
        'image' => $image->item(0)->getAttribute('src'),
        'element_after_barcode' => $part('barcode')->nextElementSibling?->getAttribute('class') ?? '',
        'text' => $text($label),
    ];
}

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(InitialDataSeeder::class);

    $this->admin = User::where('email', 'admin@alhusseini.com')->firstOrFail();
    $this->branch = Branch::where('code', 'MAIN')->firstOrFail();
    $this->purchase = labelPurchase($this->branch->id);
    $this->actingAs($this->admin);
});

test('the review modal suggests one label per received piece and one for a bulk product', function () {
    $battery = labelLine($this->purchase, labelProduct(['sku' => 'CHL-70']), 20);
    $oil = labelLine($this->purchase, labelProduct(['sku' => 'OIL-BULK', 'unit' => ProductUnit::Liter->value, 'is_battery' => false]), 12.5);

    $this->get(route('admin.purchases.show', $this->purchase))
        ->assertOk()
        ->assertSee('طباعة باركود الشحنة / التوريدة')
        ->assertSee('name="items['.$battery->id.']"', false)
        ->assertSeeInOrder(['name="items['.$battery->id.']"', 'value="20"'], false)
        ->assertSeeInOrder(['name="items['.$oil->id.']"', 'value="1"'], false)
        ->assertSee('12.5');
});

test('the suggestion excludes returned quantity', function () {
    $line = labelLine($this->purchase, labelProduct(), 20);
    $line->update(['returned_quantity' => 5]);

    $this->get(route('admin.purchases.show', $this->purchase))
        ->assertSeeInOrder(['name="items['.$line->id.']"', 'value="15"'], false);
});

test('the print page prints the edited counts and skips lines set to zero', function () {
    $first = labelLine($this->purchase, labelProduct(['name' => 'كلورايد جولد', 'capacity_ah' => '70Ah']), 20);
    $second = labelLine($this->purchase, labelProduct(['name' => 'فارتا 55Ah', 'capacity_ah' => '55Ah']), 15);
    $third = labelLine($this->purchase, labelProduct(['name' => 'صنف مستبعد']), 3);

    $response = $this->get(route('admin.purchases.labels', [
        'purchase' => $this->purchase,
        'items' => [$first->id => 2, $second->id => 3, $third->id => 0],
    ]))->assertOk();

    expect(labelCount($response->getContent()))->toBe(5);
    $response->assertSee('كلورايد جولد 70Ah') // capacity appended
        ->assertSee('فارتا 55Ah')
        ->assertDontSee('فارتا 55Ah 55Ah') // already in the name
        ->assertDontSee('صنف مستبعد')
        ->assertSee('ضمان 12 شهر');
});

test('a product label encodes the barcode, or the sku when there is none, and the POS scanner resolves it', function (?string $barcode, string $expected) {
    $product = labelProduct(['sku' => 'SKU-777', 'barcode' => $barcode]);

    $response = $this->get(route('admin.products.labels', ['product' => $product, 'copies' => 1]))
        ->assertOk()
        ->assertSee('<div class="label__code">'.$expected.'</div>', false);

    expect(labelSvgs($response->getContent())[0])->toContain("<desc>{$expected}</desc>");

    $found = $this->getJson(route('admin.pos.products.search', ['q' => $expected, 'exact_barcode' => 1]))->json('data');
    expect($found)->toHaveCount(1)->and($found[0]['id'])->toBe($product->id);
})->with([
    'own barcode' => ['6221234567890', '6221234567890'],
    'sku fallback' => [null, 'SKU-777'],
]);

test('price is optional and warranty is left out when the product has none', function () {
    $product = labelProduct(['retail_price' => 1250.5, 'warranty_months' => 0]);

    $this->get(route('admin.products.labels', ['product' => $product, 'copies' => 1, 'show_price' => 1]))
        ->assertSee('1,250.50 ج.م')
        ->assertDontSee('ضمان');

    $this->get(route('admin.products.labels', ['product' => $product, 'copies' => 1]))
        ->assertDontSee('ج.م');
});

test('each size loads a stylesheet whose page and label dimensions match the enum', function (BarcodeLabelSize $size) {
    $response = $this->get(route('admin.products.labels', ['product' => labelProduct(), 'copies' => 1, 'size' => $size->value]))
        ->assertOk()
        ->assertSee("assets/css/admin/labels/label-{$size->value}.css", false);

    $width = "{$size->widthMm()}mm";
    $height = "{$size->heightMm()}mm";
    $css = file_get_contents(public_path("assets/css/admin/labels/label-{$size->value}.css"));

    // Width first is what makes the page landscape; an orientation keyword here would invalidate the rule.
    expect($size->widthMm())->toBeGreaterThan($size->heightMm());
    expect(substr_count($css, '@page'))->toBe(1);

    expect($css)->toContain("@page { size: {$width} {$height}; margin: 0; }")
        ->toContain("--label-w: {$width};")
        ->toContain("--label-h: {$height};")
        ->toContain("--side-pad: {$size->sidePaddingMm()}mm;")
        ->toContain("--bar-h: {$size->barcodeHeightMm()}mm;");

    // Physical size, so the browser cannot rescale the bars to fit the page.
    expect(labelSvgs($response->getContent())[0])->toMatch('/<svg width="[\d.]+mm" height="'.$size->barcodeHeightMm().'mm"/');
})->with(BarcodeLabelSize::cases());

test('short codes print without warning and open the print dialog automatically', function () {
    $this->get(route('admin.products.labels', ['product' => labelProduct(['sku' => 'BAT-70']), 'copies' => 1]))
        ->assertDontSee('كود الأصناف التالية طويل')
        ->assertSee('data-auto-print', false);
});

test('a code too long for the label warns and does not open the print dialog by itself', function () {
    $product = labelProduct(['sku' => 'OIL-SHL-5W40-4L']);

    $this->get(route('admin.products.labels', ['product' => $product, 'copies' => 1, 'size' => '40x25']))
        ->assertOk()
        ->assertSee('كود الأصناف التالية طويل')
        ->assertSee('OIL-SHL-5W40-4L')
        ->assertDontSee('data-auto-print', false);

});

test('a code too dense for the small label prints at a scannable bar width on the default label', function () {
    $product = labelProduct(['sku' => 'BAT-CHL-70G']);

    $this->get(route('admin.products.labels', ['product' => $product, 'copies' => 1, 'size' => '40x25']))
        ->assertSee('كود الأصناف التالية طويل')
        ->assertDontSee('data-auto-print', false);

    $this->get(route('admin.products.labels', ['product' => $product, 'copies' => 1, 'size' => '50x25']))
        ->assertDontSee('كود الأصناف التالية طويل')
        ->assertSee('data-auto-print', false);
});

test('only the XP-237B stocks are offered, with 50x25 selected by default', function () {
    expect(array_map(fn (BarcodeLabelSize $size) => $size->value, BarcodeLabelSize::cases()))->toBe(['50x25', '40x25']);

    labelLine($this->purchase, labelProduct(), 1);

    foreach ([route('admin.purchases.show', $this->purchase), route('admin.sales.products')] as $screen) {
        $html = $this->get($screen)->assertOk()->assertDontSee('80x30', false)->getContent();

        preg_match_all('#<option value="(\d+x\d+)"\s*(selected)?[^>]*>#', $html, $options, PREG_SET_ORDER);
        $selected = array_filter($options, fn (array $option) => ($option[2] ?? '') === 'selected');

        expect(array_map(fn (array $option) => $option[1], $options))->toBe(['50x25', '40x25'])
            ->and(array_map(fn (array $option) => $option[1], array_values($selected)))->toBe(['50x25']);
    }
});

test('a 13-digit barcode keeps its quiet zone on the label and its bars inside the safe margins', function (BarcodeLabelSize $size) {
    $product = labelProduct(['barcode' => '6221234567890']);

    $html = $this->get(route('admin.products.labels', ['product' => $product, 'copies' => 1, 'size' => $size->value]))
        ->assertOk()
        ->assertDontSee('كود الأصناف التالية طويل')
        ->assertSee('data-auto-print', false)
        ->getContent();

    preg_match('/<svg width="([\d.]+)mm"/', labelSvgs($html)[0], $svg);
    $modules = (new Picqer\Barcode\Types\TypeCode128())->getBarcode('6221234567890')->getWidth();
    $moduleWidth = (float) $svg[1] / $modules;

    expect($moduleWidth)->toBeGreaterThanOrEqual(0.25)
        ->and((float) $svg[1])->toBeLessThanOrEqual($size->printableWidthMm())
        ->and((float) $svg[1] + 20 * $moduleWidth)->toBeLessThanOrEqual($size->widthMm());
})->with(BarcodeLabelSize::cases());

test('the removed 80 mm size is rejected and a label without a size prints as 50x25', function () {
    $product = labelProduct();

    $this->get(route('admin.products.labels', ['product' => $product, 'copies' => 1, 'size' => '80x30']))
        ->assertSessionHasErrors('size');

    $this->get(route('admin.products.labels', ['product' => $product, 'copies' => 1]))
        ->assertOk()
        ->assertSee('assets/css/admin/labels/label-50x25.css', false);
});

test('the safe margins measured on the printer are kept and the barcode is centred', function (BarcodeLabelSize $size) {
    $css = file_get_contents(public_path('assets/css/admin/labels/labels.css'));

    expect($size->sidePaddingMm())->toBeGreaterThanOrEqual(2.0)->toBeLessThanOrEqual(2.5)
        ->and($size->printableWidthMm())->toBe($size->widthMm() - 2 * $size->sidePaddingMm())
        ->and($css)->toContain('padding: 1mm var(--side-pad);')
        ->toContain('text-align: center;')
        ->toContain('.label__barcode img { display: block; margin: 0 auto; }');
})->with(BarcodeLabelSize::cases());

test('a product with no ASCII code cannot be printed and the reason is shown', function () {
    $product = labelProduct(['sku' => 'كود-عربي', 'barcode' => null]);

    $this->get(route('admin.products.labels', ['product' => $product, 'copies' => 1]))
        ->assertRedirect(route('admin.sales.products'))
        ->assertSessionHasErrors('labels');

    $line = labelLine($this->purchase, $product, 5);
    $this->get(route('admin.purchases.show', $this->purchase))
        ->assertSee('لا يوجد كود إنجليزي')
        ->assertSeeInOrder(['name="items['.$line->id.']"', 'value="0"', 'disabled'], false);
});

test('an ASCII sku is used when the barcode cannot be encoded', function () {
    $product = labelProduct(['sku' => 'ASCII-1', 'barcode' => '٦٢٢١']);

    $response = $this->get(route('admin.products.labels', ['product' => $product, 'copies' => 1]));

    expect(labelSvgs($response->getContent())[0])->toContain('<desc>ASCII-1</desc>');
});

test('product text cannot inject markup into the label', function () {
    $product = labelProduct(['name' => '<script>alert(1)</script>', 'sku' => '<b>X</b>']);

    $response = $this->get(route('admin.products.labels', ['product' => $product, 'copies' => 1]))
        ->assertOk()
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertDontSee('<b>X</b>', false);

    expect(labelSvgs($response->getContent())[0])->toContain('<desc>&lt;b&gt;X&lt;/b&gt;</desc>');
});

test('a line of another invoice is rejected', function () {
    $other = labelLine(labelPurchase($this->branch->id, 'LBL-INV-2'), labelProduct(), 4);

    $this->get(route('admin.purchases.labels', ['purchase' => $this->purchase, 'items' => [$other->id => 1]]))
        ->assertRedirect(route('admin.purchases.show', $this->purchase))
        ->assertSessionHasErrors('labels');
});

test('an empty selection and a job above the limit are rejected', function () {
    $first = labelLine($this->purchase, labelProduct(), 900);
    $second = labelLine($this->purchase, labelProduct(), 900);
    $show = route('admin.purchases.show', $this->purchase);

    $this->get(route('admin.purchases.labels', ['purchase' => $this->purchase, 'items' => [$first->id => 0]]))
        ->assertRedirect($show)->assertSessionHasErrors('labels');

    $this->get(route('admin.purchases.labels', ['purchase' => $this->purchase, 'items' => [$first->id => 900, $second->id => 900]]))
        ->assertRedirect($show)->assertSessionHasErrors('labels');

    $this->get(route('admin.purchases.labels', ['purchase' => $this->purchase, 'items' => [$first->id => 1001]]))
        ->assertSessionHasErrors('items.'.$first->id);

    $this->get(route('admin.products.labels', ['product' => labelProduct(), 'copies' => 0]))
        ->assertSessionHasErrors('copies');

    $this->get(route('admin.products.labels', ['product' => labelProduct(), 'copies' => 1, 'size' => '10x10']))
        ->assertSessionHasErrors('size');
});

test('printing labels does not change stock', function () {
    $product = labelProduct(['current_stock' => 7]);
    $line = labelLine($this->purchase, $product, 7);

    $this->get(route('admin.purchases.labels', ['purchase' => $this->purchase, 'items' => [$line->id => 7]]))->assertOk();

    expect((float) $product->fresh()->current_stock)->toBe(7.0);
});

test('label routes require the matching permission', function () {
    $line = labelLine($this->purchase, labelProduct(), 1);
    $noPermissions = User::factory()->create(['branch_id' => $this->branch->id]);
    $productsOnly = User::factory()->create(['branch_id' => $this->branch->id]);
    $productsOnly->givePermissionTo('products.view');
    $purchasesOnly = User::factory()->create(['branch_id' => $this->branch->id]);
    $purchasesOnly->givePermissionTo('purchases.view');

    $purchaseUrl = route('admin.purchases.labels', ['purchase' => $this->purchase, 'items' => [$line->id => 1]]);
    $productUrl = route('admin.products.labels', ['product' => $line->product_id, 'copies' => 1]);

    $this->actingAs($noPermissions)->get($purchaseUrl)->assertForbidden();
    $this->actingAs($noPermissions)->get($productUrl)->assertForbidden();
    $this->actingAs($productsOnly)->get($purchaseUrl)->assertForbidden();
    $this->actingAs($productsOnly)->get($productUrl)->assertOk();
    $this->actingAs($purchasesOnly)->get($productUrl)->assertForbidden();
    $this->actingAs($purchasesOnly)->get($purchaseUrl)->assertOk();
});

test('a branch-confined user cannot print labels of another branch invoice', function () {
    $otherBranch = Branch::factory()->create();
    $foreign = labelPurchase($otherBranch->id, 'LBL-INV-B');
    $line = labelLine($foreign, labelProduct(), 1);

    $user = User::factory()->create(['branch_id' => $this->branch->id]);
    $user->givePermissionTo('purchases.view');

    $this->actingAs($user)
        ->get(route('admin.purchases.labels', ['purchase' => $foreign, 'items' => [$line->id => 1]]))
        ->assertNotFound();
});

// ─── Label content, per size. The layout itself is measured by tests/Browser/label-layout-check.php. ───

dataset('label sizes', [
    '50x25mm' => '50x25',
    '40x25mm' => '40x25',
]);

function chlorideLabel(string $size, array $query = [], array $product = []): array
{
    $product = labelProduct(array_merge([
        'name' => 'بطارية كلورايد جولد 70 أمبير',
        'barcode' => '6221234567890',
        'retail_price' => 3850,
        'warranty_months' => 12,
    ], $product));

    $html = test()->get(route('admin.products.labels', array_merge(['product' => $product, 'copies' => 1, 'size' => $size], $query)))
        ->assertOk()
        ->getContent();

    return parsedLabel($html);
}

test('with show_price=1 the label shows the name, barcode image, readable code, warranty and price', function (string $size) {
    $label = chlorideLabel($size, ['show_price' => 1]);

    expect($label['title'])->toBe('بطارية كلورايد جولد 70 أمبير')
        ->and($label['code'])->toBe('6221234567890')
        ->and($label['alt'])->toBe('6221234567890')
        ->and($label['warranty'])->toBe('ضمان 12 شهر')
        ->and($label['price'])->toBe('3,850 ج.م')
        ->and($label['company'])->toBe('الحسيني للبطاريات');
})->with('label sizes');

test('the landscape layout shares rows across the width and prints the code directly under the barcode', function (string $size) {
    $label = chlorideLabel($size, ['show_price' => 1]);

    expect($label['rows'])->toBe(['label__header', 'label__title', 'label__barcode', 'label__footer'])
        ->and($label['element_after_barcode'])->toBe('label__footer')
        ->and($label['footer'])->toBe(['label__warranty', 'label__code']);
})->with('label sizes');

test('the barcode image is a vector SVG that encodes the printed code', function (string $size) {
    $label = chlorideLabel($size);

    expect($label['image'])->toStartWith('data:image/svg+xml;base64,');

    $svg = base64_decode(substr($label['image'], strlen('data:image/svg+xml;base64,')));
    expect($svg)->toContain('<desc>6221234567890</desc>')
        ->toContain('shape-rendering="crispEdges"');

    // Every drawn bar keeps Code 128's width ratio; the library also returns zero-width bars it does not draw.
    $bars = (new Picqer\Barcode\Types\TypeCode128())->getBarcode('6221234567890')->getBars();
    $expectedWidths = array_values(array_map(
        fn ($bar) => $bar->getWidth(),
        array_filter($bars, fn ($bar) => $bar->isBar() && $bar->getWidth() > 0),
    ));

    preg_match_all('/<rect x="[\d.]+" y="[\d.]+" width="([\d.]+)"/', $svg, $rects);
    $moduleWidth = (float) $rects[1][0] / $expectedWidths[0];
    $drawnWidths = array_map(fn (string $width) => (int) round((float) $width / $moduleWidth), $rects[1]);

    expect($expectedWidths)->not->toBeEmpty()
        ->and($drawnWidths)->toBe($expectedWidths);
})->with('label sizes');

test('the specification is added to the title when the name does not already hold it', function (string $size) {
    expect(chlorideLabel($size, [], ['name' => 'بطارية كلورايد جولد', 'capacity_ah' => '70Ah'])['title'])
        ->toBe('بطارية كلورايد جولد 70Ah');
    expect(chlorideLabel($size, [], ['name' => 'فارتا بلو 55Ah', 'capacity_ah' => '55Ah', 'barcode' => '6221234567891'])['title'])
        ->toBe('فارتا بلو 55Ah');
})->with('label sizes');

test('the capacity is not repeated when its number is already in the name', function (string $name, string $capacity, string $expected) {
    expect(chlorideLabel('50x25', [], ['name' => $name, 'capacity_ah' => $capacity])['title'])->toBe($expected);
})->with([
    'Arabic unit in the name' => ['بطارية كلورايد جولد 70 أمبير', '70Ah', 'بطارية كلورايد جولد 70 أمبير'],
    'bare capacity number' => ['بطارية كلورايد 70 أمبير جولد', '70', 'بطارية كلورايد 70 أمبير جولد'],
    'Arabic unit in the capacity' => ['فارتا 55Ah', '55 أمبير', 'فارتا 55Ah'],
    'Arabic digits in the name' => ['بطارية ٧٠ أمبير', '70Ah', 'بطارية ٧٠ أمبير'],
    'number joined to the unit' => ['Chloride Gold 70Ah', '70AH', 'Chloride Gold 70Ah'],
    'different capacity is added' => ['بطارية 55 أمبير', '70Ah', 'بطارية 55 أمبير 70Ah'],
    'number inside a larger one is added' => ['بطارية موديل 170', '70Ah', 'بطارية موديل 170 70Ah'],
    'number inside a decimal is added' => ['بطارية 7.5', '5Ah', 'بطارية 7.5 5Ah'],
    'oil grade must match as written' => ['زيت شل هيلكس', '5W-40', 'زيت شل هيلكس 5W-40'],
    'oil grade already in the name' => ['زيت شل 5W-40', '5W-40', 'زيت شل 5W-40'],
]);

test('without show_price the price disappears and the name, barcode and warranty stay intact', function (string $size, array $query) {
    $label = chlorideLabel($size, $query);

    expect($label['price'])->toBeNull()
        ->and($label['warranty'])->toBe('ضمان 12 شهر')
        ->and($label['text'])->not->toContain('ج.م')
        ->and($label['text'])->not->toContain('3,850')
        ->and($label['title'])->toBe('بطارية كلورايد جولد 70 أمبير')
        ->and($label['code'])->toBe('6221234567890')
        ->and($label['element_after_barcode'])->toBe('label__footer');
})->with('label sizes')->with([
    'show_price=0' => [['show_price' => 0]],
    'show_price absent' => [[]],
]);

test('with neither a price nor a warranty the label still shows the code under the barcode', function (string $size) {
    $label = chlorideLabel($size, ['show_price' => 0], ['warranty_months' => 0]);

    expect($label['price'])->toBeNull()
        ->and($label['warranty'])->toBeNull()
        ->and($label['footer'])->toBe(['label__code'])
        ->and($label['code'])->toBe('6221234567890');
})->with('label sizes');

test('a product without warranty shows only its price', function (string $size) {
    $label = chlorideLabel($size, ['show_price' => 1], ['warranty_months' => 0]);

    expect($label['price'])->toBe('3,850 ج.م')
        ->and($label['warranty'])->toBeNull();
})->with('label sizes');

test('the retail price is formatted in Egyptian pounds', function (int|float $price, string $expected) {
    expect(chlorideLabel('50x25', ['show_price' => 1], ['retail_price' => $price])['price'])->toBe($expected);
})->with([
    'whole amount' => [3850, '3,850 ج.م'],
    'piasters kept' => [1250.5, '1,250.50 ج.م'],
    'below a thousand' => [950, '950 ج.م'],
    'largest accepted price' => [9999999, '9,999,999 ج.م'],
]);

test('a shipment label prints the same content as a product label', function () {
    $product = labelProduct(['name' => 'بطارية كلورايد جولد 70 أمبير', 'barcode' => '6221234567890', 'retail_price' => 3850, 'warranty_months' => 12]);
    $line = labelLine($this->purchase, $product, 20);

    $html = $this->get(route('admin.purchases.labels', ['purchase' => $this->purchase, 'items' => [$line->id => 2], 'show_price' => 1]))
        ->assertOk()
        ->getContent();

    $label = parsedLabel($html);
    expect($label['title'])->toBe('بطارية كلورايد جولد 70 أمبير')
        ->and($label['code'])->toBe('6221234567890')
        ->and($label['warranty'])->toBe('ضمان 12 شهر')
        ->and($label['price'])->toBe('3,850 ج.م');
});

test('the stylesheet keeps long content from being clipped silently', function () {
    $css = file_get_contents(public_path('assets/css/admin/labels/labels.css'));
    $rule = function (string $selector) use ($css): string {
        expect(preg_match('/^'.preg_quote($selector, '/').' \{(.*?)\}/ms', $css, $match))->toBe(1);

        return $match[1];
    };

    expect($rule('.label__header'))->toContain('flex-wrap: wrap')
        ->not->toContain('overflow')
        ->and($rule('.label__company'))->not->toContain('overflow')
        ->and($rule('.label__price'))->not->toContain('overflow')
        ->and($rule('.label__footer'))->toContain('grid-template-columns: 1fr auto 1fr')
        ->not->toContain('overflow')
        ->and($rule('.label__code'))->toContain('overflow-wrap: anywhere')
        ->not->toContain('nowrap')
        ->and($rule('.label__title'))->toContain('-webkit-line-clamp: 2');
});

test('the page warns about a name cut by the label and holds the print dialog', function () {
    $response = $this->get(route('admin.products.labels', ['product' => labelProduct(), 'copies' => 1]))->assertOk();
    $response->assertSee('id="titleCutNotice"', false)->assertSee('assets/js/admin/labels-print.js', false);

    $script = file_get_contents(public_path('assets/js/admin/labels-print.js'));
    expect($script)->toContain('MAX_TITLE_LINES = 2')
        ->toContain("!hasCutTitle && document.body.hasAttribute('data-auto-print')");
});

test('the catalog offers a label button for products but not for services', function () {
    labelProduct(['name' => 'بطارية للملصق']);
    $services = Category::firstOrCreate(['slug' => 'services'], ['name' => 'خدمات']);
    $service = labelProduct(['name' => 'خدمة شحن', 'category_id' => $services->id, 'is_battery' => false]);

    $this->get(route('admin.sales.products'))
        ->assertOk()
        ->assertSee('data-action="products.labels"', false)
        ->assertDontSee('data-product-id="'.$service->id.'"', false);
});
