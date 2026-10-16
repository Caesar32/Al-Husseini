<?php

use App\Contracts\Sales\PosOrderServiceInterface;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\TechnicianCommission;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

// Phase 6 (DB-04): one invoice can hold several scrap batteries and several technician commissions.

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->admin = User::where('email', 'admin@alhusseini.com')->firstOrFail();

    $battery = Product::where('is_battery', true)->firstOrFail();
    $battery->update(['current_stock' => 5]);

    $this->invoice = app(PosOrderServiceInterface::class)->processPosSale([
        'branch_id' => Branch::first()->id,
        'technician_id' => Employee::first()->id,
        'items' => [['product_id' => $battery->id, 'quantity' => 1, 'unit_price' => 3200, 'battery_serial' => 'SN-CARD-1']],
        'has_scrap' => true,
        'scrap_capacity_ah' => 70,
        'scrap_count' => 2,
        'payments' => [['method' => 'cash', 'amount' => 1600]],
    ], $this->admin->id);
});

test('a sale with two scrap batteries keeps both on the invoice', function () {
    expect($this->invoice->scrapBatteries()->count())->toBe(2);
});

test('the owner invoice detail lists every scrap battery and keeps the deprecated single field', function () {
    Sanctum::actingAs($this->admin, ['owner:monitor']);

    $data = $this->getJson(route('api.v1.owner.sales.show', $this->invoice->id))->assertOk()->json('data');

    expect($data['scrap_batteries'])->toHaveCount(2)
        ->and($data['scrap_batteries'][0])->toHaveKeys(['capacity_ah', 'scrap_value', 'lead_weight_kg', 'status'])
        ->and($data['scrap_battery'])->toBe($data['scrap_batteries'][0]);
});

test('the admin invoice page shows the scrap battery count and both commissions are loaded', function () {
    $second = Employee::where('id', '!=', Employee::first()->id)->firstOrFail();
    foreach ([Employee::first(), $second] as $employee) {
        TechnicianCommission::forceCreate(['employee_id' => $employee->id, 'invoice_id' => $this->invoice->id, 'commission_amount' => 50]);
    }

    $this->actingAs($this->admin)->get(route('admin.invoices.show', $this->invoice))
        ->assertOk()
        ->assertSee('العدد: <strong>2</strong>', false)
        ->assertViewHas('invoice', fn (Invoice $invoice) => $invoice->technicianCommissions->count() === 2
            && $invoice->technicianCommissions->every(fn ($c) => $c->relationLoaded('employee')));
});
