<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeeHistory extends Model
{
    protected $fillable = [
        'merchant_id',
        'fee_type_id',
        'base_amount',
        'base_currency',
        'fee_amount_eur',
        'exchange_rate',
        'applied_date',
        'report_reference',
    ];

    protected $casts = [
        'applied_date' => 'datetime',
    ];
    protected $with = ['feeType'];
    public function feeType(): BelongsTo
    {
        return $this->belongsTo(FeeType::class);
    }
}
