<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RollingReserveEntry extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'merchant_id',
        'transaction_reference',
        'original_amount',
        'original_currency',
        'reserve_amount_eur',
        'exchange_rate',
        'transaction_date',
        'release_date',
        'released_at',
        'status',
    ];

    protected $casts = [
        'transaction_date' => 'datetime',
        'release_date' => 'datetime',
        'released_at' => 'datetime',
    ];
}
