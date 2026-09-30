<?php

use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\InitialDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// SEC-02: supplier and purchase routes must enforce the existing permission catalogue.

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(InitialDataSeeder::class);

    $this->supplier = Supplier::create([
        'name' => 'مورد اختبار',
        'company_name' => 'شركة اختبار',
        'phone' => '01000000001',
    ]);
    $this->cashier = User::where('email', 'cashier@alhusseini.com')->firstOrFail();
    $this->accountant = User::where('email', 'accountant@alhusseini.com')->firstOrFail();
    $this->admin = User::where('email', 'admin@alhusseini.com')->firstOrFail();
});

test('cashier cannot read or change suppliers', function () {
    $this->actingAs($this->cashier);

    $this->get(route('admin.suppliers.index'))->assertForbidden();
    $this->get(route('admin.suppliers.show', $this->supplier))->assertForbidden();
    $this->get(route('admin.suppliers.edit', $this->supplier))->assertForbidden();
    $this->post(route('admin.suppliers.store'), [
        'name' => 'x', 'company_name' => 'y', 'phone' => '01000000002',
    ])->assertForbidden();
    $this->put(route('admin.suppliers.update', $this->supplier), [
        'name' => 'x', 'company_name' => 'y', 'phone' => '01000000001',
    ])->assertForbidden();
    $this->delete(route('admin.suppliers.destroy', $this->supplier))->assertForbidden();

    expect(Supplier::count())->toBe(1)
        ->and($this->supplier->fresh()->name)->toBe('مورد اختبار');
});

test('cashier cannot read or post purchase invoices', function () {
    $this->actingAs($this->cashier);

    $this->get(route('admin.purchases.index'))->assertForbidden();
    $this->get(route('admin.purchases.create'))->assertForbidden();
    $this->post(route('admin.purchases.store'), [])->assertForbidden();
});

test('accountant can view suppliers and purchases but cannot create them', function () {
    $this->actingAs($this->accountant);

    $this->get(route('admin.suppliers.index'))->assertOk();
    $this->get(route('admin.purchases.index'))->assertOk();

    $this->post(route('admin.suppliers.store'), [
        'name' => 'x', 'company_name' => 'y', 'phone' => '01000000003',
    ])->assertForbidden();
    $this->get(route('admin.purchases.create'))->assertForbidden();
    $this->delete(route('admin.suppliers.destroy', $this->supplier))->assertForbidden();
});

test('super admin can create a supplier', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.suppliers.store'), [
            'name' => 'مورد جديد',
            'company_name' => 'شركة جديدة',
            'phone' => '01000000004',
            'credit_limit' => 0,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(Supplier::where('phone', '01000000004')->exists())->toBeTrue();
});

test('removed routes are not registered', function () {
    expect(\Illuminate\Support\Facades\Route::has('admin.suppliers.create'))->toBeFalse()
        ->and(\Illuminate\Support\Facades\Route::has('admin.roles.show'))->toBeFalse();
});
