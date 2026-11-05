<?php

use App\Contracts\Hr\PayrollServiceInterface;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\EmployeeDeduction;
use App\Models\EmployeeLeave;
use App\Models\Payroll;
use App\Models\PayrollItem;
use App\Models\User;
use Database\Seeders\InitialDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(InitialDataSeeder::class);

    $this->admin = User::where('email', 'admin@alhusseini.com')->firstOrFail();
    $this->branch = Branch::where('code', 'MAIN')->firstOrFail();
    $this->actingAs($this->admin);
});

test('a stale draft cannot be approved after the batch has moved on', function (string $current) {
    $stale = Payroll::factory()->create([
        'branch_id' => $this->branch->id,
        'year' => 2026,
        'month' => 1,
        'status' => 'draft',
        'total_basic' => 0,
        'total_allowances' => 0,
        'total_deductions' => 0,
        'total_net' => 0,
    ]);

    Payroll::whereKey($stale->id)->update(['status' => $current]);

    expect(fn () => app(PayrollServiceInterface::class)->approvePayroll($stale, $this->admin->id))
        ->toThrow(Exception::class);

    expect($stale->fresh()->status)->toBe($current);
})->with(['approved', 'disbursed']);

test('approving updates the given model and records the approver', function () {
    $payroll = Payroll::factory()->create([
        'branch_id' => $this->branch->id,
        'year' => 2026,
        'month' => 2,
        'status' => 'draft',
        'total_basic' => 0,
        'total_allowances' => 0,
        'total_deductions' => 0,
        'total_net' => 0,
    ]);

    expect(app(PayrollServiceInterface::class)->approvePayroll($payroll, $this->admin->id))->toBeTrue()
        ->and($payroll->status)->toBe('approved')
        ->and($payroll->fresh()->status)->toBe('approved')
        ->and($payroll->fresh()->approved_by)->toBe($this->admin->id);
});

/** Queries of one GET request, so a list that queries per row grows with its rows. */
function hrQueryCount(string $url): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    test()->getJson($url)->assertOk();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

test('HR lists run the same number of queries for more rows', function (string $route, Closure $seedRows) {
    $seedRows($this->branch, 3);
    hrQueryCount(route($route)); // The first request also fills the permission cache.
    $few = hrQueryCount(route($route));

    $seedRows($this->branch, 9);
    $many = hrQueryCount(route($route));

    expect($many)->toBe($few);
})->with([
    'employees' => ['admin.hr.employees', function (Branch $branch, int $rows) {
        Employee::factory()->count($rows)->create(['branch_id' => $branch->id]);
    }],
    'leaves' => ['admin.hr.leaves.index', function (Branch $branch, int $rows) {
        EmployeeLeave::factory()->count($rows)->create(['employee_id' => Employee::factory()->create(['branch_id' => $branch->id])->id]);
    }],
    'deductions' => ['admin.hr.deductions.index', function (Branch $branch, int $rows) {
        EmployeeDeduction::factory()->count($rows)->create(['employee_id' => Employee::factory()->create(['branch_id' => $branch->id])->id]);
    }],
    'payroll register' => ['admin.hr.payroll', function (Branch $branch, int $rows) {
        static $year = 2000;
        foreach (range(1, $rows) as $row) {
            $payroll = Payroll::factory()->create(['branch_id' => $branch->id, 'year' => ++$year, 'month' => 1]);
            PayrollItem::factory()->create([
                'payroll_id' => $payroll->id,
                'employee_id' => Employee::factory()->create(['branch_id' => $branch->id])->id,
            ]);
        }
    }],
]);
