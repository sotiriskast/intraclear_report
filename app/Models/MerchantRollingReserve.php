<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MerchantRollingReserve extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'merchant_id',
        'percentage',
        'holding_period_days',
        'currency',
        'effective_from',
        'effective_to',
        'active',
    ];

    protected $casts = [
        'effective_from' => 'datetime',
        'effective_to' => 'datetime',
        'active' => 'boolean',
    ];
}
