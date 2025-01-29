<?php

namespace App\Repositories;

use App\Models\FeeType;

class FeeTypeRepository
{
    /**
     * Get standard fee types mapped by their keys
     *
     * @return array
     */
    public function getStandardFeeTypes(): array
    {
        return FeeType::whereIn('key', [
            'mdr_fee',
            'transaction_fee',
            'declined_fee',
            'payout_fee',
            'refund_fee',
            'chargeback_fee',
            'monthly_fee',
            'mastercard_high_risk_fee',
            'visa_high_risk_fee',
            'setup_fee',
        ])->get()->keyBy('key')->toArray();
    }
}
