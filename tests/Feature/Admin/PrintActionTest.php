<?php

use App\Contracts\Sales\PosOrderServiceInterface;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Frontend F4: print buttons use data-action="print" (no inline onclick). Standalone print pages,
// which do not load the admin core, bind it with assets/js/print.js.

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->admin = User::where('email', 'admin@alhusseini.com')->firstOrFail();
});

test('the receipt and warranty certificate print through print.js', function () {
    $battery = Product::where('is_battery', true)->firstOrFail();
    $battery->update(['current_stock' => 3]);
    $invoice = app(PosOrderServiceInterface::class)->processPosSale([
        'branch_id' => Branch::first()->id,
        'technician_id' => Employee::first()->id,
        'items' => [['product_id' => $battery->id, 'quantity' => 1, 'unit_price' => 3200, 'battery_serial' => 'SN-PRINT-1']],
        'payments' => [['method' => 'cash', 'amount' => 3200]],
    ], $this->admin->id);

    foreach ([route('admin.pos.receipt', $invoice), route('admin.pos.warranty_cert', $invoice)] as $url) {
        $this->actingAs($this->admin)->get($url)
            ->assertOk()
            ->assertSee('data-action="print"', false)
            ->assertSee(asset('assets/js/print.js'), false)
            ->assertDontSee('onclick="window.print', false);
    }
});

test('no view prints through an inline onclick any more', function () {
    $hits = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views'), FilesystemIterator::SKIP_DOTS)) as $file) {
        if (str_contains(file_get_contents($file->getPathname()), 'onclick="window.print')) {
            $hits[] = $file->getPathname();
        }
    }

    expect($hits)->toBe([]);
});
