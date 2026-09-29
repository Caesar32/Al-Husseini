<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeePayrollDebt extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'source_payroll_item_id',
        'original_amount',
        'paid_amount',
        'settled_at',
    ];

    protected function casts(): array
    {
        return [
            'original_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'settled_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function sourcePayrollItem(): BelongsTo
    {
        return $this->belongsTo(PayrollItem::class, 'source_payroll_item_id');
    }

    public function remainingAmount(): float
    {
        return max(0, (float) $this->original_amount - (float) $this->paid_amount);
    }
}
