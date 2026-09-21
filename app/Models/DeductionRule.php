<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeductionRule extends Model
{
    protected $fillable = [
        'name',
        'type',
        'calculation_method',
        'multiplier_value',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'multiplier_value' => 'decimal:2',
        ];
    }

    public function deductions(): HasMany
    {
        return $this->hasMany(EmployeeDeduction::class);
    }
}
