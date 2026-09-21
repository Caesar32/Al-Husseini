<?php

namespace Database\Factories;

use App\Models\PayrollItem;
use App\Models\Payroll;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PayrollItem>
 */
class PayrollItemFactory extends Factory
{
    protected $model = PayrollItem::class;

    public function definition(): array
    {
        return [
            'payroll_id' => Payroll::factory(),
            'employee_id' => Employee::factory(),
            'basic_salary' => 8000,
            'total_allowance' => 1200,
            'total_deduction' => 400,
            'total_overtime' => 200,
            'net_salary' => 9000,
            'absent_days' => 0,
            'late_minutes_total' => 30,
        ];
    }
}
