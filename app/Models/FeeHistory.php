<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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

    public function feeType()
    {
        return $this->belongsTo(FeeType::class);
    }
}
