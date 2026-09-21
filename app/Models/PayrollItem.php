<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollItem extends Model
{
    use HasFactory;
    protected $fillable = [
        'payroll_id',
        'employee_id',
        'basic_salary',
        'total_allowance',
        'total_deduction',
        'total_overtime',
        'net_salary',
        'absent_days',
        'late_minutes_total',
    ];

    protected function casts(): array
    {
        return [
            'basic_salary' => 'decimal:2',
            'total_allowance' => 'decimal:2',
            'total_deduction' => 'decimal:2',
            'total_overtime' => 'decimal:2',
            'net_salary' => 'decimal:2',
            'absent_days' => 'integer',
            'late_minutes_total' => 'integer',
        ];
    }

    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
