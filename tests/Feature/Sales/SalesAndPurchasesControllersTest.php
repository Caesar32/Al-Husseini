<?php

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warranty;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

test('guests are redirected to login when attempting to access sales and purchasing routes', function () {
    $this->get(route('admin.suppliers.index'))->assertRedirect(route('admin.login'));
    $this->get(route('admin.purchases.index'))->assertRedirect(route('admin.login'));
    $this->get(route('admin.pos.index'))->assertRedirect(route('admin.login'));
    $this->get(route('admin.invoices.index'))->assertRedirect(route('admin.login'));
    $this->get(route('admin.warranties.index'))->assertRedirect(route('admin.login'));
    $this->get(route('admin.scrap.index'))->assertRedirect(route('admin.login'));
});

test('user without permissions is forbidden 403 from accessing protected operations', function () {
    $user = User::factory()->create([
        'branch_id' => Branch::first()->id,
        'is_active' => true,
    ]);

    // Regular user without permissions
    $this->actingAs($user)
        ->get(route('admin.pos.index'))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('admin.invoices.index'))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('admin.warranties.index'))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('admin.scrap.index'))
        ->assertForbidden();
});

test('super admin can access pos page, invoices list, suppliers, and warranty verification', function () {
    $superAdmin = User::where('email', 'admin@alhusseini.com')->first();
    if (!$superAdmin) {
        $superAdmin = User::factory()->create(['branch_id' => Branch::first()->id, 'is_active' => true]);
        $superAdmin->assignRole('super-admin');
    }

    $this->actingAs($superAdmin)
        ->get(route('admin.pos.index'))
        ->assertOk();

    $this->actingAs($superAdmin)
        ->get(route('admin.invoices.index'))
        ->assertOk();

    $this->actingAs($superAdmin)
        ->get(route('admin.warranties.verify', ['serial_number' => 'NON-EXISTENT']))
        ->assertOk()
        ->assertJson([
            'exists'   => false,
            'is_valid' => false,
        ]);
});

test('pos store endpoint creates invoice and returns 201 json payload', function () {
    $superAdmin = User::where('email', 'admin@alhusseini.com')->first();
    $battery = Product::where('is_battery', true)->first();
    $battery->update(['current_stock' => 10, 'retail_price' => 3000]);

    $response = $this->actingAs($superAdmin)
        ->postJson(route('admin.pos.store'), [
            'items' => [
                [
                    'product_id'     => $battery->id,
                    'quantity'       => 1,
                    'unit_price'     => 3000,
                    'battery_serial' => 'SN-HTTP-TEST-99',
                ],
            ],
            'payments' => [
                ['method' => 'cash', 'amount' => 3000],
            ],
        ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'success',
            'message',
            'invoice_id',
            'invoice_number',
            'receipt_url',
            'warranty_cert_url',
        ]);

    $this->assertDatabaseHas('invoices', [
        'subtotal'       => 3000,
        'final_amount'   => 3000,
        'payment_method' => 'cash',
    ]);

    $this->assertDatabaseHas('warranties', [
        'serial_number' => 'SN-HTTP-TEST-99',
        'status'        => 'active',
    ]);
});

test('warranty verification endpoint accurately finds active warranty and returns customer vehicle data', function () {
    $superAdmin = User::where('email', 'admin@alhusseini.com')->first();
    $customer = Customer::create([
        'name'                   => 'عميل فحص الضمان',
        'phone'                  => '01099881122',
        'credit_limit'           => 1000,
        'current_credit_balance' => 0,
        'tier'                   => 'standard',
        'is_active'              => true,
    ]);

    $branch = Branch::first();
    $invoice = Invoice::create([
        'invoice_number' => 'INV-VERIFY-01',
        'branch_id'      => $branch->id,
        'customer_id'    => $customer->id,
        'cashier_id'     => $superAdmin->id,
        'subtotal'       => 2500,
        'final_amount'   => 2500,
        'paid_amount'    => 2500,
    ]);

    $item = InvoiceItem::create([
        'invoice_id'              => $invoice->id,
        'product_id'              => Product::first()->id,
        'quantity'                => 1,
        'unit_price'              => 2500,
        'total_price'             => 2500,
        'battery_serial_number'   => 'SN-VERIFY-ACTIVE-01',
        'warranty_duration_months'=> 12,
    ]);

    Warranty::create([
        'invoice_item_id' => $item->id,
        'customer_id'     => $customer->id,
        'serial_number'   => 'SN-VERIFY-ACTIVE-01',
        'start_date'      => now()->toDateString(),
        'end_date'        => now()->addMonths(12)->toDateString(),
        'status'          => 'active',
    ]);

    $response = $this->actingAs($superAdmin)
        ->getJson(route('admin.warranties.verify', ['serial_number' => 'SN-VERIFY-ACTIVE-01']));

    $response->assertOk()
        ->assertJson([
            'exists'   => true,
            'is_valid' => true,
        ]);
});

test('supplier store endpoint creates new supplier', function () {
    $superAdmin = User::where('email', 'admin@alhusseini.com')->first();

    $response = $this->actingAs($superAdmin)
        ->postJson(route('admin.suppliers.store'), [
            'name'         => 'محي الدين البشبيشي',
            'company_name' => 'شركة الدلتا للتوزيع والبطاريات',
            'phone'        => '01099887711',
            'credit_limit' => 150000,
        ]);

    $response->assertStatus(201)
        ->assertJson([
            'success' => true,
        ]);

    $this->assertDatabaseHas('suppliers', [
        'company_name' => 'شركة الدلتا للتوزيع والبطاريات',
        'phone'        => '01099887711',
    ]);
});

test('warranty verify web route renders dedicated html verification view', function () {
    $superAdmin = User::where('email', 'admin@alhusseini.com')->first();

    $response = $this->actingAs($superAdmin)
        ->get(route('admin.warranties.verify'));

    $response->assertOk()
        ->assertViewIs('admin.warranties.verify')
        ->assertSee('فحص الضمان والاستبدال السريع')
        ->assertSee('فحص صلاحية وسريان سيريال البطارية فورياً');
});

