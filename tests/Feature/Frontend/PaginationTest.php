<?php

use App\Models\Branch;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\User;
use Database\Seeders\InitialDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(InitialDataSeeder::class);

    $this->actingAs(User::where('email', 'admin@alhusseini.com')->firstOrFail());
    $this->branch = Branch::where('code', 'MAIN')->firstOrFail();
});

function sixteenPayrolls(Branch $branch): void
{
    foreach (range(1, 16) as $offset) {
        Payroll::factory()->create([
            'branch_id' => $branch->id,
            'year' => 2000 + $offset,
            'month' => 1,
        ]);
    }
}

test('pagination links use the Bootstrap markup of the admin theme', function () {
    sixteenPayrolls($this->branch);

    $this->get(route('admin.hr.payroll'))
        ->assertOk()
        ->assertSee('class="pagination"', false)
        ->assertSee('page-link', false)
        ->assertDontSee('sm:hidden', false);
});

test('the payroll register links to its later pages and keeps the branch filter', function () {
    sixteenPayrolls($this->branch);

    $html = $this->get(route('admin.hr.payroll', ['branch_id' => $this->branch->id]))->assertOk()->getContent();

    expect(html_entity_decode($html))->toContain("branch_id={$this->branch->id}&page=2");
});

test('employee, leave and deduction pages keep the query string in their links', function (string $route, string $paginatorKey) {
    if ($paginatorKey === 'employees') {
        Employee::factory()->count(16)->create(['branch_id' => $this->branch->id, 'status' => 'active']);
    }

    $json = $this->getJson(route($route, ['status' => 'active', 'probe' => 'kept']))->assertOk()->json();
    $paginator = $paginatorKey === '' ? $json : $json[$paginatorKey];

    expect($paginator['first_page_url'])->toContain('status=active')->toContain('probe=kept');
})->with([
    'employees' => ['admin.hr.employees', 'employees'],
    'leaves' => ['admin.hr.leaves.index', ''],
    'deductions' => ['admin.hr.deductions.index', ''],
]);

test('the credit collections register keeps the query string in its paginator', function () {
    $paginator = app(App\Services\Sales\CreditCollectionService::class);
    request()->query->set('probe', 'kept');

    expect($paginator->paginateCollections()->url(1))->toContain('probe=kept');
});
