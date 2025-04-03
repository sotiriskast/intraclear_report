<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MerchantSetting extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'merchant_id',
        'rolling_reserve_percentage',
        'holding_period_days',
        'mdr_percentage',
        'transaction_fee',
        'payout_fee',
        'refund_fee',
        'chargeback_fee',
        'monthly_fee',
        'mastercard_high_risk_fee_applied',
        'visa_high_risk_fee_applied',
        'setup_fee',
        'setup_fee_charged',
        'declined_fee',
        'exchange_rate_markup',
        'fx_rate_markup',
    ];

    protected $casts = [
        'active' => 'boolean',
        'setup_fee_charged' => 'boolean',
    ];

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }
}
