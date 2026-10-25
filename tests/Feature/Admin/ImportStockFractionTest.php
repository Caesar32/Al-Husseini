<?php

use App\Enums\ProductUnit;
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
    $category = Category::firstOrCreate(['slug' => 'stock-import-fractional'], ['name' => 'اختبار استيراد الكسور']);

    $this->liter = Product::create([
        'category_id' => $category->id,
        'sku' => 'IMPORT-FRAC-L',
        'name' => 'لتر استيراد',
        'brand' => 'اختبار',
        'unit' => ProductUnit::Liter->value,
        'cost_price' => 10,
        'retail_price' => 20,
        'current_stock' => 1,
        'is_battery' => false,
        'is_active' => true,
    ]);
    $this->piece = Product::create([
        'category_id' => $category->id,
        'sku' => 'IMPORT-FRAC-P',
        'name' => 'قطعة استيراد',
        'brand' => 'اختبار',
        'unit' => ProductUnit::Piece->value,
        'cost_price' => 10,
        'retail_price' => 20,
        'current_stock' => 1,
        'is_battery' => false,
        'is_active' => true,
    ]);
});

function fractionalImportSheet(array $rows): string
{
    $path = tempnam(sys_get_temp_dir(), 'stk-frac').'.xlsx';
    $writer = new Writer();
    $writer->openToFile($path);
    $writer->addRow(Row::fromValues(['sku', 'actual_stock']));
    foreach ($rows as $row) {
        $writer->addRow(Row::fromValues($row));
    }
    $writer->close();

    return $path;
}

test('stock import dry-run accepts decimal counts for a fractional product', function () {
    $file = fractionalImportSheet([['IMPORT-FRAC-L', 2.555]]);

    $this->artisan('inventory:import-stock', ['--file' => $file, '--dry-run' => true])
        ->expectsOutputToContain('Matched products')
        ->expectsOutputToContain('DRY RUN: nothing was written')
        ->assertSuccessful();

    @unlink($file);
    expect($this->liter->fresh()->current_stock)->toBe(1);
});

test('stock import reports a fractional count for a piece product as invalid', function () {
    $file = fractionalImportSheet([['IMPORT-FRAC-P', 1.5]]);

    $this->artisan('inventory:import-stock', ['--file' => $file, '--dry-run' => true])
        ->expectsOutputToContain('Invalid value')
        ->expectsOutputToContain('DRY RUN: nothing was written')
        ->assertSuccessful();

    @unlink($file);
    expect($this->piece->fresh()->current_stock)->toBe(1);
});
