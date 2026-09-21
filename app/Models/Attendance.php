<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class Attendance extends Model
{
    use HasFactory;
    protected $fillable = [
        'employee_id',
        'work_date',
        'check_in',
        'check_out',
        'late_minutes',
        'early_leave_minutes',
        'overtime_hours',
        'status',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'check_in' => 'datetime',
            'check_out' => 'datetime',
            'late_minutes' => 'integer',
            'early_leave_minutes' => 'integer',
            'overtime_hours' => 'decimal:2',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function deductions(): HasMany
    {
        return $this->hasMany(EmployeeDeduction::class);
    }

    public function scopeLateToday(Builder $query): void
    {
        $query->where('work_date', today())->where('late_minutes', '>', 0);
    }

    public function scopeAbsentToday(Builder $query): void
    {
        $query->where('work_date', today())->where('status', 'absent');
    }

    public function scopePresentToday(Builder $query): void
    {
        $query->where('work_date', today())->where('status', 'present');
    }
}
