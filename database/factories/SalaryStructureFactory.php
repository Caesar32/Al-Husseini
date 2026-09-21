<?php

namespace Database\Factories;

use App\Models\SalaryStructure;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SalaryStructure>
 */
class SalaryStructureFactory extends Factory
{
    protected $model = SalaryStructure::class;

    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'basic_salary' => fake()->randomElement([7000, 8000, 9000, 10000, 12000, 15000]),
            'housing_allowance' => fake()->randomElement([500, 1000, 1500]),
            'transport_allowance' => fake()->randomElement([300, 500, 800]),
            'other_allowances' => 0,
            'effective_from' => now()->startOfYear()->toDateString(),
            'is_current' => true,
        ];
    }
}
