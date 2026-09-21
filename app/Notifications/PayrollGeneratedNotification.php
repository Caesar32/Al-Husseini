<?php

namespace App\Notifications;

use App\Models\Payroll;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PayrollGeneratedNotification extends Notification
{
    use Queueable;

    public function __construct(public Payroll $payroll) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'payroll_generated',
            'title' => 'مسير رواتب جاهز للمراجعة',
            'message' => "تم توليد مسودة مسير رواتب فرع {$this->payroll->branch->name} لشهر {$this->payroll->month}/{$this->payroll->year} بإجمالي {$this->payroll->total_net} ج.م ومطلوب اعتماده.",
            'payroll_id' => $this->payroll->id,
            'branch_name' => $this->payroll->branch->name,
            'total_net' => $this->payroll->total_net,
            'time' => now()->toTimeString(),
            'icon' => 'ri-wallet-3-line',
            'color' => 'success',
            'url' => route('hr.payroll.show', $this->payroll->id),
        ];
    }
}
