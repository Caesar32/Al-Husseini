<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerVehicle extends Model
{
    protected $fillable = [
        'customer_id',
        'plate_number',
        'car_brand',
        'car_model',
        'model_year',
        'chassis_number',
        'last_odometer',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'model_year' => 'integer',
            'last_odometer' => 'integer',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function warranties(): HasMany
    {
        return $this->hasMany(Warranty::class);
    }
}
