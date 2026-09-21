<?php

namespace Database\Factories;

use App\Models\EmployeeDeduction;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EmployeeDeduction>
 */
class EmployeeDeductionFactory extends Factory
{
    protected $model = EmployeeDeduction::class;

    public function definition(): array
    {
        $reasons = [
            'تأخير عن موعد فتح المعرض',
            'إهمال في فحص كفاءة البطارية ودينامو السيارة',
            'تلف كابل فحص وشحن أثناء العمل بالورشة',
            'غياب غير مبرر في يوم ذروة العمل',
        ];

        return [
            'employee_id' => Employee::factory(),
            'deduction_rule_id' => null,
            'attendance_id' => null,
            'deduction_date' => now()->toDateString(),
            'amount' => fake()->randomElement([100, 200, 300, 500]),
            'reason' => fake()->randomElement($reasons),
            'approved_by' => User::factory(),
            'status' => 'approved',
        ];
    }

    public function approved(): static
    {
        return $this->state(fn() => ['status' => 'approved']);
    }

    public function cancelled(): static
    {
        return $this->state(fn() => ['status' => 'cancelled']);
    }
}
