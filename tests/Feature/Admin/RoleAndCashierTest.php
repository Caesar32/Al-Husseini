<?php

use App\Models\Branch;
use App\Models\Employee;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\InitialDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(InitialDataSeeder::class);
    $this->seed(\Database\Seeders\SalesAndPosDataSeeder::class);
});

test('cashier user is redirected directly to pos index upon login', function () {
    $cashier = User::where('email', 'cashier@alhusseini.com')->first();
    expect($cashier)->not->toBeNull();
    expect($cashier->hasRole('cashier'))->toBeTrue();

    $response = $this->post(route('admin.login.submit'), [
        'email'    => 'cashier@alhusseini.com',
        'password' => '12345678',
    ]);

    $response->assertRedirect(route('admin.pos.index'));
    $this->assertAuthenticatedAs($cashier);
});

test('super admin user is redirected to dashboard upon login', function () {
    $admin = User::where('email', 'admin@alhusseini.com')->first();

    $response = $this->post(route('admin.login.submit'), [
        'email'    => 'admin@alhusseini.com',
        'password' => '12345678',
    ]);

    $response->assertRedirect(route('admin.dashboard'));
    $this->assertAuthenticatedAs($admin);
});

test('cashier accessing root route is redirected to pos index', function () {
    $cashier = User::where('email', 'cashier@alhusseini.com')->first();

    $response = $this->actingAs($cashier)->get('/');
    $response->assertRedirect(route('admin.pos.index'));
});

test('cashier cannot apply discount without supervisor override code', function () {
    $cashier = User::where('email', 'cashier@alhusseini.com')->first();
    $technician = Employee::first();
    $battery = Product::where('is_battery', true)->first();
    $battery->update(['current_stock' => 10, 'retail_price' => 3000]);

    $response = $this->actingAs($cashier)->postJson(route('admin.pos.store'), [
        'branch_id' => \App\Models\Branch::first()->id,
        'technician_id' => $technician->id,
        'items' => [
            [
                'product_id'     => $battery->id,
                'quantity'       => 1,
                'unit_price'     => 3000,
                'battery_serial' => 'SN-CASHIER-TEST-01',
            ],
        ],
        'discount_amount' => 200, // Cashier trying to give 200 discount
        'payments' => [
            ['method' => 'cash', 'amount' => 2800],
        ],
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['discount_amount']);
});

test('cashier can apply discount with valid supervisor override code', function () {
    $cashier = User::where('email', 'cashier@alhusseini.com')->first();
    $technician = Employee::first();
    $battery = Product::where('is_battery', true)->first();
    $battery->update(['current_stock' => 10, 'retail_price' => 3000]);

    // Override secret must be configured explicitly (no built-in default code).
    config(['finance.manager_override_hash' => \Illuminate\Support\Facades\Hash::make('supervisor-secret')]);

    $response = $this->actingAs($cashier)->postJson(route('admin.pos.store'), [
        'branch_id' => \App\Models\Branch::first()->id,
        'technician_id'         => $technician->id,
        'items' => [
            [
                'product_id'     => $battery->id,
                'quantity'       => 1,
                'unit_price'     => 3000,
                'battery_serial' => 'SN-CASHIER-TEST-02',
            ],
        ],
        'discount_amount'       => 200,
        'manager_override_code' => 'supervisor-secret',
        'payments' => [
            ['method' => 'cash', 'amount' => 2800],
        ],
    ]);

    $response->assertStatus(201)
        ->assertJson([
            'success' => true,
        ]);
});

test('admin can view roles index and see system roles', function () {
    $admin = User::where('email', 'admin@alhusseini.com')->first();

    $response = $this->actingAs($admin)->get(route('admin.roles.index'));
    $response->assertOk()
        ->assertSee('إدارة الأدوار وتحديد الصلاحيات')
        ->assertSee('super-admin')
        ->assertSee('cashier')
        ->assertSee('accountant');
});

test('admin can create a new custom role with selected permissions', function () {
    $admin = User::where('email', 'admin@alhusseini.com')->first();

    $response = $this->actingAs($admin)->post(route('admin.roles.store'), [
        'name'        => 'inventory-assistant',
        'permissions' => ['products.view', 'scrap.view'],
    ]);

    $response->assertRedirect(route('admin.roles.index'));
    $this->assertDatabaseHas('roles', ['name' => 'inventory-assistant']);

    $newRole = Role::findByName('inventory-assistant');
    expect($newRole->hasPermissionTo('products.view'))->toBeTrue();
    expect($newRole->hasPermissionTo('scrap.view'))->toBeTrue();
    expect($newRole->hasPermissionTo('invoices.discount'))->toBeFalse();
});

test('admin can update permissions of an existing role', function () {
    $admin = User::where('email', 'admin@alhusseini.com')->first();
    $cashierRole = Role::findByName('cashier');

    // Give cashier the discount permission
    $response = $this->actingAs($admin)->put(route('admin.roles.update', $cashierRole), [
        'permissions' => [
            'pos.access',
            'invoices.view',
            'invoices.create',
            'invoices.print',
            'invoices.discount', // Added discount permission!
        ],
    ]);

    $response->assertRedirect(route('admin.roles.index'));
    $cashierRole->refresh();
    expect($cashierRole->hasPermissionTo('invoices.discount'))->toBeTrue();
});

test('admin cannot delete system roles', function () {
    $admin = User::where('email', 'admin@alhusseini.com')->first();
    $cashierRole = Role::findByName('cashier');

    $response = $this->actingAs($admin)->delete(route('admin.roles.destroy', $cashierRole));
    $response->assertRedirect(route('admin.roles.index'));
    $this->assertDatabaseHas('roles', ['name' => 'cashier']);
});

test('admin can view users list and assign roles', function () {
    $admin = User::where('email', 'admin@alhusseini.com')->first();
    $cashier = User::where('email', 'cashier@alhusseini.com')->first();

    $response = $this->actingAs($admin)->get(route('admin.users.index'));
    $response->assertOk()
        ->assertSee($cashier->name)
        ->assertSee('كاشير مبيعات');

    // Switch cashier to accountant
    $updateRoleResponse = $this->actingAs($admin)->post(route('admin.users.role', $cashier), [
        'role' => 'accountant',
    ]);

    $updateRoleResponse->assertRedirect(route('admin.users.index'));
    $cashier->refresh();
    expect($cashier->hasRole('accountant'))->toBeTrue();
    expect($cashier->hasRole('cashier'))->toBeFalse();
});

test('admin can toggle user active status', function () {
    $admin = User::where('email', 'admin@alhusseini.com')->first();
    $cashier = User::where('email', 'cashier@alhusseini.com')->first();

    $response = $this->actingAs($admin)->post(route('admin.users.toggle-status', $cashier));
    $response->assertRedirect(route('admin.users.index'));

    $cashier->refresh();
    expect($cashier->is_active)->toBeFalse();
});

test('user without roles manage permission cannot access roles management', function () {
    $cashier = User::where('email', 'cashier@alhusseini.com')->first();

    $response = $this->actingAs($cashier)->get(route('admin.roles.index'));
    $response->assertStatus(403);
});

test('user can log in using their phone number', function () {
    $user = User::factory()->create([
        'name'      => 'فني تشخيص ورشة',
        'email'     => 'technician@alhusseini.com',
        'phone'     => '01099887766',
        'password'  => bcrypt('customPassword123'),
        'is_active' => true,
    ]);
    $user->assignRole('workshop-supervisor');

    $response = $this->post(route('admin.login.submit'), [
        'login'    => '01099887766',
        'password' => 'customPassword123',
    ]);

    $response->assertRedirect();
    $this->assertAuthenticatedAs($user);
});

test('admin can reset any specialist password', function () {
    $admin = User::where('email', 'admin@alhusseini.com')->first();
    $cashier = User::where('email', 'cashier@alhusseini.com')->first();

    $response = $this->actingAs($admin)->post(route('admin.users.reset-password', $cashier), [
        'password' => 'newSecretPass789',
    ]);

    $response->assertRedirect(route('admin.users.index'));
    $response->assertSessionHas('status');

    $cashier->refresh();
    expect(\Illuminate\Support\Facades\Hash::check('newSecretPass789', $cashier->password))->toBeTrue();
});

test('register route redirects to login with restricted admin creation notice', function () {
    $response = $this->get(route('admin.register'));
    $response->assertRedirect(route('admin.login'));
    $response->assertSessionHas('info');
});
