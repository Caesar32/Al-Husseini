<?php

use App\Models\Category;
use App\Models\Product;
use Database\Seeders\InitialDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(InitialDataSeeder::class);
});

function stockProduct(string $sku, int $stock = 0, ?string $barcode = null): Product
{
    return Product::create([
        'category_id'   => Category::firstOrCreate(['slug' => 'batteries'], ['name' => 'بطاريات'])->id,
        'sku'           => $sku,
        'barcode'       => $barcode,
        'name'          => 'منتج ' . $sku,
        'brand'         => 'B',
        'cost_price'    => 100,
        'retail_price'  => 150,
        'current_stock' => $stock,
    ]);
}

function stockSheet(array $rows): string
{
    $path = tempnam(sys_get_temp_dir(), 'stk') . '.xlsx';
    $w = new Writer();
    $w->openToFile($path);
    $w->addRow(Row::fromValues(['sku', 'barcode', 'name', 'actual_stock']));
    foreach ($rows as $r) {
        $w->addRow(Row::fromValues($r));
    }
    $w->close();

    return $path;
}

test('dry run reports but writes nothing', function () {
    $p = stockProduct('A1');
    $file = stockSheet([['A1', '', 'x', 7]]);

    $this->artisan('inventory:import-stock', ['--file' => $file, '--dry-run' => true])->assertExitCode(0);

    expect($p->fresh()->current_stock)->toBe(0);
});

test('default without --apply is read only', function () {
    $p = stockProduct('A1');
    $this->artisan('inventory:import-stock', ['--file' => stockSheet([['A1', '', 'x', 7]])])->assertExitCode(0);
    expect($p->fresh()->current_stock)->toBe(0);
});

test('apply updates stock, ignores blanks, rejects negatives and fractions, and writes a backup', function () {
    $ok = stockProduct('OK', 3);
    $blank = stockProduct('BLANK', 4);
    $neg = stockProduct('NEG', 5);
    $frac = stockProduct('FRAC', 6);
    $zero = stockProduct('ZERO', 9);
    $file = stockSheet([
        ['OK', '', 'x', 12],
        ['BLANK', '', 'x', ''],
        ['NEG', '', 'x', -2],
        ['FRAC', '', 'x', 2.5],
        ['ZERO', '', 'x', 0],
        ['GHOST', '', 'x', 4],
    ]);
    $before = glob(storage_path('app/private/backups/stock_backup_*.json')) ?: [];

    $this->artisan('inventory:import-stock', ['--file' => $file, '--apply' => true])->assertExitCode(0);

    expect($ok->fresh()->current_stock)->toBe(12)
        ->and($blank->fresh()->current_stock)->toBe(4)
        ->and($neg->fresh()->current_stock)->toBe(5)
        ->and($frac->fresh()->current_stock)->toBe(6)
        ->and($zero->fresh()->current_stock)->toBe(0);

    $new = array_values(array_diff(glob(storage_path('app/private/backups/stock_backup_*.json')), $before));
    expect($new)->toHaveCount(1);
    $data = json_decode(file_get_contents($new[0]), true);
    $byId = collect($data['products'])->keyBy('sku');
    expect($byId['OK']['previous_stock'])->toBe(3)->and($byId['ZERO']['previous_stock'])->toBe(9);
    @unlink($new[0]);
});

test('barcode is only a fallback and duplicate rows keep the first value', function () {
    $p = stockProduct('B1', 0, '555');
    $file = stockSheet([['WRONG', '555', 'x', 8], ['B1', '', 'x', 99]]);

    $this->artisan('inventory:import-stock', ['--file' => $file, '--apply' => true])->assertExitCode(0);

    expect($p->fresh()->current_stock)->toBe(8);
    array_map('unlink', glob(storage_path('app/private/backups/stock_backup_*.json')) ?: []);
});

test('missing file and conflicting flags fail', function () {
    $this->artisan('inventory:import-stock', ['--file' => 'nope.xlsx'])->assertExitCode(1);
    $this->artisan('inventory:import-stock', ['--file' => stockSheet([]), '--apply' => true, '--dry-run' => true])->assertExitCode(1);
});
