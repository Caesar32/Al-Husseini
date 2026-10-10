<?php

use App\Models\Branch;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\Payroll;
use App\Models\User;
use Database\Seeders\InitialDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// SEC-08: users confined to a branch only see and resolve that branch's records.

function makeInvoice(int $branchId, int $cashierId, string $number): Invoice
{
    return Invoice::forceCreate([
        'invoice_number' => $number, 'branch_id' => $branchId, 'cashier_id' => $cashierId,
        'subtotal' => 100, 'final_amount' => 100, 'paid_amount' => 100,
    ]);
}

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(InitialDataSeeder::class);

    $this->branchA = Branch::where('code', 'MAIN')->firstOrFail();
    $this->branchB = Branch::factory()->create();
    $this->admin = User::where('email', 'admin@alhusseini.com')->firstOrFail();

    // Created while unauthenticated (unrestricted) so both branches have data.
    $this->invoiceA = makeInvoice($this->branchA->id, $this->admin->id, 'ISO-A');
    $this->invoiceB = makeInvoice($this->branchB->id, $this->admin->id, 'ISO-B');
    $this->employeeA = Employee::factory()->create(['branch_id' => $this->branchA->id]);
    $this->employeeB = Employee::factory()->create(['branch_id' => $this->branchB->id]);
    Payroll::factory()->create(['branch_id' => $this->branchB->id, 'year' => 2026, 'month' => 9]);

    $this->managerA = User::factory()->create(['branch_id' => $this->branchA->id]);
    $this->managerA->assignRole('branch-manager');
});

test('a branch-confined user only sees records of their branch', function () {
    $this->actingAs($this->managerA);

    expect(Invoice::pluck('invoice_number')->all())->toBe(['ISO-A'])
        ->and(Employee::pluck('id')->all())->toContain($this->employeeA->id)
        ->not->toContain($this->employeeB->id)
        ->and(Payroll::count())->toBe(0);
});

test('route model binding returns 404 for another branch and 200 for their own', function () {
    $this->actingAs($this->managerA);

    $this->get(route('admin.invoices.show', $this->invoiceA))->assertOk();
    $this->get(route('admin.invoices.show', $this->invoiceB))->assertNotFound();
    $this->getJson(route('admin.hr.employees.show', $this->employeeA))->assertOk();
    $this->getJson(route('admin.hr.employees.show', $this->employeeB))->assertNotFound();
});

test('invoice listing for a confined user excludes other branches', function () {
    $numbers = collect($this->actingAs($this->managerA)->getJson(route('admin.invoices.index'))->assertOk()->json('data'))
        ->pluck('invoice_number');

    expect($numbers->all())->toBe(['ISO-A']);
});

test('super-admin and users without a branch see every branch', function () {
    $noBranch = User::factory()->create(['branch_id' => null]);
    $noBranch->assignRole('branch-manager');

    foreach ([$this->admin, $noBranch] as $user) {
        $this->actingAs($user);
        expect(Invoice::count())->toBe(2);
        $this->get(route('admin.invoices.show', $this->invoiceB))->assertOk();
    }
});

test('a client-supplied branch cannot place a new record in another branch', function () {
    $this->actingAs($this->managerA);

    $created = makeInvoice($this->branchB->id, $this->managerA->id, 'ISO-FORCED');

    expect($created->branch_id)->toBe($this->branchA->id);
});

test('console and unauthenticated contexts are unrestricted', function () {
    expect(Invoice::count())->toBe(2)
        ->and(Employee::whereIn('id', [$this->employeeA->id, $this->employeeB->id])->count())->toBe(2);
});

test('isolation can be switched off by configuration', function () {
    config(['app.branch_isolation' => false]);
    $this->actingAs($this->managerA);

    expect(Invoice::count())->toBe(2);
});

test('a confined user cannot generate payroll or create employees for another branch', function () {
    $this->managerA->givePermissionTo('employees.create'); // not part of branch-manager by default
    $this->actingAs($this->managerA);

    $this->postJson(route('admin.hr.payroll.generate'), ['branch_id' => $this->branchB->id, 'year' => 2026, 'month' => 8])
        ->assertStatus(422)
        ->assertJsonValidationErrors('branch_id');

    $this->postJson(route('admin.hr.employees.store'), ['branch_id' => $this->branchB->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('branch_id');

    expect(Payroll::withoutGlobalScopes()->where('branch_id', $this->branchB->id)->where('month', 8)->exists())->toBeFalse();
});

test('a confined user can still generate payroll for their own branch', function () {
    $this->actingAs($this->managerA);

    $this->postJson(route('admin.hr.payroll.generate'), ['branch_id' => $this->branchA->id, 'year' => 2026, 'month' => 8])
        ->assertSuccessful();

    expect(Payroll::where('branch_id', $this->branchA->id)->where('month', 8)->exists())->toBeTrue();
});
