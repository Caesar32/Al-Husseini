<?php

use App\Models\User;
use Database\Seeders\InitialDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SalesAndPosDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// FE-01/02/03/05/08: business screens must use server data; the browser-only mock stores
// (sales-store.js / hr-store.js seed fake products, customers and employees into localStorage)
// must not be loaded on any page.

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(InitialDataSeeder::class);
    $this->seed(SalesAndPosDataSeeder::class);
    $this->actingAs(User::where('email', 'admin@alhusseini.com')->firstOrFail());
});

test('admin pages do not load or reference the mock stores', function (string $routeName) {
    $html = $this->get(route($routeName))->assertOk()->getContent();

    expect($html)
        ->not->toContain('assets/js/sales-store.js')
        ->not->toContain('assets/js/hr-store.js')
        ->not->toContain('AlHusseiniSales')
        ->not->toContain('AlHusseiniHR');
})->with([
    'admin.dashboard',
    'admin.pos.index',
    'admin.credit.index',
    'admin.invoices.index',
    'admin.sales.products',
    'admin.sales.customers',
    'admin.hr.reports',
    'admin.hr.payroll',
    'admin.hr.employees',
    'admin.hr.attendance',
    'admin.suppliers.index',
]);
