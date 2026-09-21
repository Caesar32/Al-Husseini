<?php

namespace Database\Factories;

use App\Models\EmployeeLeave;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EmployeeLeave>
 */
class EmployeeLeaveFactory extends Factory
{
    protected $model = EmployeeLeave::class;

    public function definition(): array
    {
        $start = now()->addDays(fake()->numberBetween(1, 10));
        $days = fake()->numberBetween(1, 5);
        $end = (clone $start)->addDays($days - 1);

        return [
            'employee_id' => Employee::factory(),
            'leave_type' => fake()->randomElement(['annual', 'sick', 'emergency', 'unpaid']),
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'days_count' => $days,
            'reason' => 'ظروف شخصية عائلية',
            'status' => 'pending',
            'actioned_by' => null,
            'action_notes' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn() => [
            'status' => 'approved',
            'actioned_by' => User::factory(),
            'action_notes' => 'موافقة الإدارة',
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn() => [
            'status' => 'rejected',
            'actioned_by' => User::factory(),
            'action_notes' => 'اعتذار لضغط العمل',
        ]);
    }
}
