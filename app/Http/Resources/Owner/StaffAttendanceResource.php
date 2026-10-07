<?php

namespace App\Http\Resources\Owner;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StaffAttendanceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $checkIn = $this->check_in;
        $checkInTime = $checkIn ? $checkIn->format('h:i') . ' ' . ($checkIn->format('A') === 'AM' ? 'ص' : 'م') : null;

        $checkOut = $this->check_out;
        $checkOutTime = $checkOut ? $checkOut->format('h:i') . ' ' . ($checkOut->format('A') === 'AM' ? 'ص' : 'م') : null;

        $status = $this->status ?? 'absent';
        $statusLabels = [
            'present' => 'حاضر',
            'late'    => 'متأخر',
            'absent'  => 'غائب',
            'leave'   => 'إجازة',
        ];

        return [
            'employee_id'    => $this->employee?->id ?? $this->employee_id,
            'employee_code'  => $this->employee?->employee_code,
            'name'           => $this->employee?->full_name ?? 'موظف',
            'role'           => $this->employee?->jobTitle?->title ?? 'فني / موظف',
            'phone'          => $this->employee?->phone,
            'status'         => $status,
            'status_label'   => $statusLabels[$status] ?? $status,
            'check_in_time'  => $checkInTime,
            'check_out_time' => $checkOutTime,
            'late_minutes'   => (int) ($this->late_minutes ?? 0),
        ];
    }
}
