<?php

use App\Models\CreditLedgerEntry;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\SupplierLedgerEntry;
use App\Models\User;
use App\Services\Diagnostics\SystemDiagnosticService;
use Database\Seeders\InitialDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SalesAndPosDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(InitialDataSeeder::class);
    $this->seed(SalesAndPosDataSeeder::class);
    $this->actingAs(User::where('email', 'admin@alhusseini.com')->firstOrFail());

    $this->diagnostics = app(SystemDiagnosticService::class);
    // Real purchases, supplier payments, credit sales, settlements and warranty credit notes.
    $simulation = $this->diagnostics->runLiveSimulation(false);
    expect($simulation['passed_count'])->toBe($simulation['total_steps']);
});

function ledgerCheck(array $audit, string $key): array
{
    return collect($audit['checks'])->firstWhere('key', $key);
}

test('supplier and customer ledgers produced by real flows pass the chain checks', function () {
    expect(SupplierLedgerEntry::count())->toBeGreaterThan(0)
        ->and(CreditLedgerEntry::count())->toBeGreaterThan(0);

    $audit = $this->diagnostics->runFullAudit();

    expect(ledgerCheck($audit, 'supplier_ledger')['status'])->toBe('passed')
        ->and(ledgerCheck($audit, 'customer_credit_ledger')['status'])->toBe('passed');
});

test('a stored balance that disagrees with the ledger is reported', function () {
    $supplier = Supplier::whereIn('id', SupplierLedgerEntry::distinct()->pluck('supplier_id'))->firstOrFail();
    $supplier->update(['current_balance' => (float) $supplier->current_balance + 50]);

    $customer = Customer::whereIn('id', CreditLedgerEntry::distinct()->pluck('customer_id'))->firstOrFail();
    $customer->update(['current_credit_balance' => (float) $customer->current_credit_balance + 50]);

    $audit = $this->diagnostics->runFullAudit();

    expect(ledgerCheck($audit, 'supplier_ledger')['count'])->toBe(1)
        ->and(ledgerCheck($audit, 'customer_credit_ledger')['count'])->toBe(1);
});

test('a broken running balance inside the ledger is reported even when the final balance matches', function () {
    $entry = SupplierLedgerEntry::orderBy('id')->firstOrFail();
    // Amount no longer explains the movement from balance_before to balance_after.
    $entry->update(['amount' => (float) $entry->amount + 10]);

    expect(ledgerCheck($this->diagnostics->runFullAudit(), 'supplier_ledger')['count'])->toBe(1);
});
