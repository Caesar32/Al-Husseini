<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Branch;
use App\Models\JobTitle;
use App\Models\SalaryStructure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Employee>
 */
class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    public function definition(): array
    {
        $arabicFirstNames = ['أحمد', 'محمد', 'محمود', 'إبراهيم', 'مصطفى', 'كريم', 'طارق', 'حسين', 'علي', 'عمر', 'يوسف', 'خالد'];
        $arabicLastNames = ['الحسيني', 'الشريف', 'السيد', 'عبدالرحمن', 'منصور', 'إبراهيم', 'حسن', 'رمضان', 'عثمان', 'عفيفي', 'فاروق'];

        $name = fake()->randomElement($arabicFirstNames) . ' ' . fake()->randomElement($arabicLastNames);

        return [
            'branch_id' => Branch::factory(),
            'job_title_id' => JobTitle::factory(),
            'employee_code' => 'EMP-' . fake()->unique()->numberBetween(1000, 999999),
            'full_name' => $name,
            'national_id' => '2' . fake()->numberBetween(80, 99) . fake()->numerify('###########'),
            'phone' => '01' . fake()->randomElement(['0', '1', '2', '5']) . fake()->numerify('########'),
            'hire_date' => fake()->dateTimeBetween('-3 years', '-1 month')->format('Y-m-d'),
            'shift_start_time' => '09:00:00',
            'shift_end_time' => '17:00:00',
            'grace_period_minutes' => 15,
            'zkteco_pin' => (string) fake()->unique()->numberBetween(1000, 99999),
            'status' => 'active',
        ];
    }

    public function active(): static
    {
        return $this->state(fn() => ['status' => 'active']);
    }

    public function onLeave(): static
    {
        return $this->state(fn() => ['status' => 'on_leave']);
    }

    public function terminated(): static
    {
        return $this->state(fn() => ['status' => 'terminated']);
    }

    public function withSalary(float $basic = 8000, float $housing = 1000, float $transport = 500): static
    {
        return $this->afterCreating(function (Employee $employee) use ($basic, $housing, $transport) {
            SalaryStructure::create([
                'employee_id' => $employee->id,
                'basic_salary' => $basic,
                'housing_allowance' => $housing,
                'transport_allowance' => $transport,
                'other_allowances' => 0,
                'effective_from' => $employee->hire_date,
                'is_current' => true,
            ]);
        });
    }
}
