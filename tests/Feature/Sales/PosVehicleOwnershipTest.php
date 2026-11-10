<?php

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerVehicle;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Database\Seeders\InitialDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SalesAndPosDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(InitialDataSeeder::class);
    $this->seed(SalesAndPosDataSeeder::class);

    $this->cashier = User::where('email', 'cashier@alhusseini.com')->firstOrFail();
    $this->oil = Product::where('is_battery', false)->whereHas('category', fn ($query) => $query->where('slug', 'oils'))->firstOrFail();
    $this->oil->update(['current_stock' => 10, 'retail_price' => 500]);
    $this->technician = Employee::firstOrFail();
    $this->branch = Branch::firstOrFail();

    $this->customerA = Customer::create([
        'name' => 'Customer A',
        'phone' => '01099990001',
        'credit_limit' => 100000,
        'current_credit_balance' => 0,
        'tier' => 'standard',
        'is_active' => true,
    ]);
    $this->customerB = Customer::create([
        'name' => 'Customer B',
        'phone' => '01099990002',
        'credit_limit' => 100000,
        'current_credit_balance' => 0,
        'tier' => 'standard',
        'is_active' => true,
    ]);
    $this->vehicleA = CustomerVehicle::create([
        'customer_id' => $this->customerA->id,
        'plate_number' => 'A-1234',
        'car_brand' => 'Toyota',
        'car_model' => 'Corolla',
        'model_year' => 2024,
    ]);
    $this->vehicleB = CustomerVehicle::create([
        'customer_id' => $this->customerB->id,
        'plate_number' => 'B-5678',
        'car_brand' => 'Hyundai',
        'car_model' => 'Elantra',
        'model_year' => 2024,
    ]);
});

test('returns 422 when a vehicle belongs to another customer', function () {
    $invoicesBefore = Invoice::count();
    $movementsBefore = StockMovement::where('product_id', $this->oil->id)->count();

    $this->actingAs($this->cashier)->postJson(route('admin.pos.store'), [
        'branch_id' => $this->branch->id,
        'technician_id' => $this->technician->id,
        'customer_id' => $this->customerA->id,
        'customer_vehicle_id' => $this->vehicleB->id,
        'items' => [['product_id' => $this->oil->id, 'quantity' => 1, 'unit_price' => 500]],
        'payments' => [['method' => 'cash', 'amount' => 500]],
    ])->assertStatus(422)->assertJsonValidationErrors(['customer_vehicle_id']);

    expect(Invoice::count())->toBe($invoicesBefore)
        ->and($this->oil->fresh()->current_stock)->toBe(10)
        ->and(StockMovement::where('product_id', $this->oil->id)->count())->toBe($movementsBefore);
});

test('creates an invoice when the vehicle belongs to the sale customer', function () {
    $response = $this->actingAs($this->cashier)->postJson(route('admin.pos.store'), [
        'branch_id' => $this->branch->id,
        'technician_id' => $this->technician->id,
        'customer_id' => $this->customerA->id,
        'customer_vehicle_id' => $this->vehicleA->id,
        'items' => [['product_id' => $this->oil->id, 'quantity' => 1, 'unit_price' => 500]],
        'payments' => [['method' => 'cash', 'amount' => 500]],
    ])->assertStatus(201);

    expect(Invoice::findOrFail($response->json('invoice_id'))->customer_vehicle_id)->toBe($this->vehicleA->id);
});

test('returns 422 when a walk-in sale carries a vehicle', function () {
    $invoicesBefore = Invoice::count();

    $this->actingAs($this->cashier)->postJson(route('admin.pos.store'), [
        'branch_id' => $this->branch->id,
        'technician_id' => $this->technician->id,
        'customer_id' => null,
        'customer_vehicle_id' => $this->vehicleA->id,
        'items' => [['product_id' => $this->oil->id, 'quantity' => 1, 'unit_price' => 500]],
        'payments' => [['method' => 'cash', 'amount' => 500]],
    ])->assertStatus(422)->assertJsonValidationErrors(['customer_vehicle_id']);

    expect(Invoice::count())->toBe($invoicesBefore);
});

test('creates an invoice for a customer without a vehicle', function () {
    $this->actingAs($this->cashier)->postJson(route('admin.pos.store'), [
        'branch_id' => $this->branch->id,
        'technician_id' => $this->technician->id,
        'customer_id' => $this->customerA->id,
        'items' => [['product_id' => $this->oil->id, 'quantity' => 1, 'unit_price' => 500]],
        'payments' => [['method' => 'cash', 'amount' => 500]],
    ])->assertStatus(201);
});
