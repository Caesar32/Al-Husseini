<?php

namespace App\Contracts\Hr;

use App\Models\Attendance;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

interface AttendanceServiceInterface
{
    /**
     * Get attendance records for a specific date and optional branch / status filters.
     */
    public function getDailyAttendance(string $date, ?int $branchId = null, ?string $status = null): Collection;

    /**
     * Get attendance KPI statistics for a given date.
     */
    public function getDailyStats(string $date): array;

    /**
     * Get reference form data (active branches, active employees with branch).
     */
    public function getFormData(): array;

    /**
     * Record a biometric or manual punch in/out and calculate lateness/early departure.
     */
    public function recordPunch(mixed $employeeIdentifier, Carbon $punchTime, string|int $punchState, string $source = 'manual'): Attendance;
}
