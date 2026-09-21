<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeDeduction extends Model
{
    use HasFactory;
    protected $fillable = [
        'employee_id',
        'deduction_rule_id',
        'attendance_id',
        'deduction_date',
        'amount',
        'reason',
        'approved_by',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'deduction_date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function deductionRule(): BelongsTo
    {
        return $this->belongsTo(DeductionRule::class);
    }

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
