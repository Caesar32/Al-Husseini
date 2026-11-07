<?php

use App\Models\Customer;
use App\Models\User;
use App\Services\Sales\CustomerService;
use Database\Seeders\InitialDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Legacy customers have no phone on record. phone != x alone would drop them, because a NULL
// compared with anything is not true.

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(InitialDataSeeder::class);

    $this->admin = User::where('email', 'admin@alhusseini.com')->firstOrFail();
    $this->noPhone = Customer::create(['name' => 'عميل بدون هاتف', 'phone' => null, 'tier' => 'standard', 'is_active' => true]);
    $this->withPhone = Customer::create(['name' => 'عميل له هاتف', 'phone' => '01099990002', 'tier' => 'standard', 'is_active' => true]);
    $this->walkIn = Customer::firstOrCreate(['phone' => CustomerService::WALK_IN_PHONE], ['name' => 'عميل نقدي عابر', 'tier' => 'standard', 'is_active' => true]);
});

test('the scope keeps customers with no phone and drops only the walk-in account', function () {
    $ids = Customer::notWalkIn()->pluck('id');

    expect($ids)->toContain($this->noPhone->id)
        ->toContain($this->withPhone->id)
        ->not->toContain($this->walkIn->id);
});

test('the POS page lists customers with no phone', function () {
    $response = $this->actingAs($this->admin)->get(route('admin.pos.index'))->assertOk();
    $customers = collect($response->viewData('customers'));

    expect($customers->pluck('id'))->toContain($this->noPhone->id)
        ->not->toContain($this->walkIn->id)
        ->and($customers->firstWhere('id', $this->noPhone->id)['phone'])->toBeNull();
});

test('an inactive customer with no phone stays out of the POS', function () {
    $this->noPhone->update(['is_active' => false]);

    $customers = collect($this->actingAs($this->admin)->get(route('admin.pos.index'))->viewData('customers'));

    expect($customers->pluck('id'))->not->toContain($this->noPhone->id);
});

test('customer search finds a customer with no phone', function () {
    $found = app(CustomerService::class)->search('بدون هاتف');

    expect($found->pluck('id'))->toContain($this->noPhone->id);
});

test('a customer with no phone can be edited without inventing one', function () {
    $this->actingAs($this->admin)
        ->putJson(route('admin.customers.update', $this->noPhone), ['name' => 'اسم جديد', 'tier' => 'vip', 'phone' => null])
        ->assertSuccessful();

    expect($this->noPhone->fresh())
        ->name->toBe('اسم جديد')
        ->phone->toBeNull();
});

test('a phone can be added later, and from then on it stays required', function () {
    $this->actingAs($this->admin)
        ->putJson(route('admin.customers.update', $this->noPhone), ['name' => 'عميل بدون هاتف', 'tier' => 'standard', 'phone' => '01099990003'])
        ->assertSuccessful();

    $this->actingAs($this->admin)
        ->putJson(route('admin.customers.update', $this->noPhone), ['name' => 'عميل بدون هاتف', 'tier' => 'standard', 'phone' => null])
        ->assertStatus(422)
        ->assertJsonValidationErrors('phone');
});

test('a new customer still needs a phone', function () {
    $this->actingAs($this->admin)
        ->postJson(route('admin.customers.store'), ['name' => 'عميل جديد', 'tier' => 'standard'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('phone');
});

test('several customers can have no phone without breaking the unique index', function () {
    $another = Customer::create(['name' => 'عميل آخر بدون هاتف', 'phone' => null, 'tier' => 'standard', 'is_active' => true]);

    expect($another->exists)->toBeTrue()
        ->and(Customer::whereNull('phone')->count())->toBeGreaterThanOrEqual(2);
});
