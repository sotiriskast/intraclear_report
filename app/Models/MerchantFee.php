<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MerchantFee extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'merchant_id',
        'fee_type_id',
        'amount',
        'effective_from',
        'effective_to',
        'active',
    ];

    protected $casts = [
        'effective_from' => 'datetime',
        'effective_to' => 'datetime',
        'active' => 'boolean',
    ];

    public function feeType()
    {
        return $this->belongsTo(FeeType::class);
    }
}
