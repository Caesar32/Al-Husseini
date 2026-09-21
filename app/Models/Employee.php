<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Builder;

class Employee extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'branch_id',
        'job_title_id',
        'user_id',
        'employee_code',
        'full_name',
        'national_id',
        'phone',
        'hire_date',
        'shift_start_time',
        'shift_end_time',
        'grace_period_minutes',
        'zkteco_pin',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'hire_date' => 'date',
            'grace_period_minutes' => 'integer',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function jobTitle(): BelongsTo
    {
        return $this->belongsTo(JobTitle::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function salaryStructures(): HasMany
    {
        return $this->hasMany(SalaryStructure::class);
    }

    public function currentSalary(): HasOne
    {
        return $this->hasOne(SalaryStructure::class)->where('is_current', true);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function deductions(): HasMany
    {
        return $this->hasMany(EmployeeDeduction::class);
    }

    public function leaves(): HasMany
    {
        return $this->hasMany(EmployeeLeave::class);
    }

    public function technicianInvoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'technician_id');
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(TechnicianCommission::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('status', 'active');
    }

    public function scopeTechnicians(Builder $query): void
    {
        $query->whereHas('jobTitle', fn($q) => $q->where('title', 'like', '%فني%')->orWhere('title', 'like', '%كهربائي%'));
    }
}
