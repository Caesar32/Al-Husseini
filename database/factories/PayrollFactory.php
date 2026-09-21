<?php

namespace Database\Factories;

use App\Models\Payroll;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Payroll>
 */
class PayrollFactory extends Factory
{
    protected $model = Payroll::class;

    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'year' => 2026,
            'month' => fake()->numberBetween(1, 12),
            'total_basic' => 50000,
            'total_allowances' => 5000,
            'total_deductions' => 2000,
            'total_net' => 53000,
            'status' => 'draft',
            'approved_by' => null,
            'disbursed_at' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn() => ['status' => 'draft']);
    }

    public function approved(): static
    {
        return $this->state(fn() => [
            'status' => 'approved',
            'approved_by' => User::factory(),
        ]);
    }

    public function disbursed(): static
    {
        return $this->state(fn() => [
            'status' => 'disbursed',
            'approved_by' => User::factory(),
            'disbursed_at' => now(),
        ]);
    }
}
