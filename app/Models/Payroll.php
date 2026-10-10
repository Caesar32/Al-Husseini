<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payroll extends Model
{
    use \App\Models\Concerns\BelongsToBranch;

    use HasFactory;
    protected $fillable = [
        'branch_id',
        'year',
        'month',
        'total_basic',
        'total_allowances',
        'total_deductions',
        'total_net',
        'status',
        'approved_by',
        'disbursed_at',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'total_basic' => 'decimal:2',
            'total_allowances' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'total_net' => 'decimal:2',
            'disbursed_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayrollItem::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(TechnicianCommission::class);
    }
}
