<?php

use App\Contracts\Hr\PayrollServiceInterface;
use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\SalaryStructure;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\InitialDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SalesAndPosDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// BIZ-16: settings whose seeded value equals the previously hard-coded behaviour now take effect.

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(InitialDataSeeder::class);
    $this->admin = User::where('email', 'admin@alhusseini.com')->firstOrFail();
    $this->actingAs($this->admin);
});

test('a missing setting does not cache the first caller default', function () {
    expect(Setting::get('not_configured_key', 'first'))->toBe('first')
        ->and(Setting::get('not_configured_key', 'second'))->toBe('second');
});

test('numeric settings fall back to the previous constant when missing or out of range', function () {
    expect(Setting::number('monthly_working_days', 26, 1, 31))->toBe(26.0);

    Setting::set('monthly_working_days', '99', 'hr');
    expect(Setting::number('monthly_working_days', 26, 1, 31))->toBe(26.0);

    Setting::set('monthly_working_days', 'abc', 'hr');
    expect(Setting::number('monthly_working_days', 26, 1, 31))->toBe(26.0);

    Setting::set('monthly_working_days', '22', 'hr');
    expect(Setting::number('monthly_working_days', 26, 1, 31))->toBe(22.0);
});

function payrollItemFor(int $workingDaysPresent, ?string $workingDaysSetting, ?string $overtimeMultiplier = null)
{
    if ($workingDaysSetting !== null) {
        Setting::set('monthly_working_days', $workingDaysSetting, 'hr');
    }
    if ($overtimeMultiplier !== null) {
        Setting::set('overtime_rate_multiplier', $overtimeMultiplier, 'hr');
    }

    $branch = Branch::factory()->create();
    $employee = Employee::factory()->create(['branch_id' => $branch->id]);
    SalaryStructure::factory()->create([
        'employee_id' => $employee->id, 'basic_salary' => 6000, 'housing_allowance' => 0,
        'transport_allowance' => 0, 'other_allowances' => 0, 'effective_from' => '2026-01-01', 'is_current' => true,
    ]);
    foreach (range(1, $workingDaysPresent) as $day) {
        Attendance::create([
            'employee_id' => $employee->id,
            'work_date' => sprintf('2026-09-%02d', $day),
            'status' => 'present',
            'overtime_hours' => $day === 1 ? 2 : 0,
        ]);
    }

    $payroll = app(PayrollServiceInterface::class)->generateMonthlyPayroll($branch->id, 2026, 9);

    return $payroll->items()->where('employee_id', $employee->id)->firstOrFail();
}

test('payroll uses the monthly working days setting (default 26 unchanged)', function () {
    // 22 present days: 4 unexcused absences under the default 26-day norm…
    expect(payrollItemFor(22, null)->absent_days)->toBe(4);
});

test('payroll absence norm follows monthly_working_days when configured', function () {
    // …and none when the norm is set to 22.
    expect(payrollItemFor(22, '22')->absent_days)->toBe(0);
});

test('payroll overtime uses overtime_rate_multiplier', function () {
    // 6000 / 30 days / 8 h = 25 per hour; 2 overtime hours.
    expect((float) payrollItemFor(26, null)->total_overtime)->toBe(75.0)        // × 1.5 default
        ->and((float) payrollItemFor(26, null, '2')->total_overtime)->toBe(100.0); // × 2
});

test('sales invoice numbers use the configured invoice prefix', function () {
    $this->seed(SalesAndPosDataSeeder::class);
    Setting::set('invoice_prefix', 'SAL-', 'sales');

    $battery = Product::where('is_battery', true)->firstOrFail();
    $battery->update(['current_stock' => 5, 'retail_price' => 1000]);

    $this->postJson(route('admin.pos.store'), [
        'branch_id' => Branch::first()->id,
        'technician_id' => Employee::first()->id,
        'items' => [['product_id' => $battery->id, 'quantity' => 1, 'unit_price' => 1000, 'battery_serial' => 'SN-PREFIX-1']],
        'payments' => [['method' => 'cash', 'amount' => 1000]],
    ])->assertStatus(201);

    expect(Invoice::latest('id')->value('invoice_number'))->toStartWith('SAL-' . now()->format('Ymd') . '-');
});
