<?php

namespace Database\Factories;

use App\Models\Attendance;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Attendance>
 */
class AttendanceFactory extends Factory
{
    protected $model = Attendance::class;

    public function definition(): array
    {
        $date = fake()->dateTimeBetween('-1 month', 'now')->format('Y-m-d');

        return [
            'employee_id' => Employee::factory(),
            'work_date' => $date,
            'check_in' => "{$date} 09:00:00",
            'check_out' => "{$date} 17:00:00",
            'late_minutes' => 0,
            'early_leave_minutes' => 0,
            'overtime_hours' => 0,
            'status' => 'present',
            'source' => 'biometric',
        ];
    }

    public function onTime(): static
    {
        return $this->state(fn() => [
            'status' => 'present',
            'late_minutes' => 0,
        ]);
    }

    public function late(int $minutes = 30): static
    {
        return $this->state(function (array $attributes) use ($minutes) {
            $date = $attributes['work_date'];
            $checkInHour = 9;
            $checkInMin = $minutes;
            return [
                'check_in' => sprintf('%s %02d:%02d:00', $date, $checkInHour, $checkInMin),
                'late_minutes' => $minutes,
                'status' => 'late',
            ];
        });
    }

    public function absent(): static
    {
        return $this->state(fn() => [
            'check_in' => null,
            'check_out' => null,
            'late_minutes' => 0,
            'status' => 'absent',
        ]);
    }

    public function overtime(float $hours = 2.0): static
    {
        return $this->state(function (array $attributes) use ($hours) {
            $date = $attributes['work_date'];
            $checkOutHour = 17 + (int)$hours;
            return [
                'check_out' => sprintf('%s %02d:00:00', $date, $checkOutHour),
                'overtime_hours' => $hours,
                'status' => 'present',
            ];
        });
    }
}
