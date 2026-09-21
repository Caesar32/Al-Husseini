<?php

namespace App\Notifications;

use App\Models\Attendance;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\DatabaseMessage;

class EmployeeLateNotification extends Notification
{
    use Queueable;

    public function __construct(public Attendance $attendance) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $employee = $this->attendance->employee;
        return [
            'type' => 'employee_late',
            'title' => 'تنبيه تأخير موظف',
            'message' => "الموظف {$employee->full_name} تأخر بمقدار {$this->attendance->late_minutes} دقيقة عن شفت اليوم.",
            'employee_id' => $employee->id,
            'employee_name' => $employee->full_name,
            'late_minutes' => $this->attendance->late_minutes,
            'work_date' => $this->attendance->work_date->toDateString(),
            'time' => now()->toTimeString(),
            'icon' => 'ri-time-line',
            'color' => 'warning',
            'url' => route('admin.hr.attendance'),
        ];
    }
}
