<?php

namespace App\Notifications;

use App\Models\EmployeeLeave;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LeaveRequestedNotification extends Notification
{
    use Queueable;

    public function __construct(public EmployeeLeave $leave) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $employee = $this->leave->employee;
        return [
            'type' => 'leave_requested',
            'title' => 'طلب إجازة جديد',
            'message' => "قدم الموظف {$employee->full_name} طلب إجازة لمدة {$this->leave->days_count} يوم تبدأ من {$this->leave->start_date->format('Y-m-d')}.",
            'leave_id' => $this->leave->id,
            'employee_name' => $employee->full_name,
            'days_count' => $this->leave->days_count,
            'time' => now()->toTimeString(),
            'icon' => 'ri-calendar-event-line',
            'color' => 'info',
            'url' => route('hr.leaves.index'),
        ];
    }
}
