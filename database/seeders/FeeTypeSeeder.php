<?php

namespace Database\Seeders;

use App\Models\FeeType;
use Illuminate\Database\Seeder;

class FeeTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $feeTypes = [
            [
                'name' => 'MDR Fee',
                'key' => 'mdr_fee',
                'frequency_type' => 'weekly',
                'is_percentage' => true,
            ],
            [
                'name' => 'Transaction Fee',
                'key' => 'transaction_fee',
                'frequency_type' => 'transaction',
                'is_percentage' => false,
            ],
            [
                'name' => 'Monthly Fee',
                'key' => 'monthly_fee',
                'frequency_type' => 'monthly',
                'is_percentage' => false,
            ],
            [
                'name' => 'Setup Fee',
                'key' => 'setup_fee',
                'frequency_type' => 'one_time',
                'is_percentage' => false,
            ],
            [
                'name' => 'Payout Fee',
                'key' => 'payout_fee',
                'frequency_type' => 'transaction',
                'is_percentage' => false,
            ],
            [
                'name' => 'Refund Fee',
                'key' => 'refund_fee',
                'frequency_type' => 'transaction',
                'is_percentage' => false,
            ],
            [
                'name' => 'Declined Fee',
                'key' => 'declined_fee',
                'frequency_type' => 'transaction',
                'is_percentage' => false,
            ],
            [
                'name' => 'Chargeback Fee',
                'key' => 'chargeback_fee',
                'frequency_type' => 'transaction',
                'is_percentage' => false,
            ],
            [
                'name' => 'Mastercard High Risk Fee',
                'key' => 'mastercard_high_risk_fee',
                'frequency_type' => 'monthly',
                'is_percentage' => false,
            ],
            [
                'name' => 'Visa High Risk Fee',
                'key' => 'visa_high_risk_fee',
                'frequency_type' => 'monthly',
                'is_percentage' => false,
            ],
        ];

        foreach ($feeTypes as $feeType) {
            FeeType::query()->firstOrCreate(
                ['key' => $feeType['key']],
                $feeType
            );
        }
    }
}
