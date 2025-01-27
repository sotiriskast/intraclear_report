<?php

namespace Database\Seeders;

use App\Models\FeeType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
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
                'name' => 'MDR%',
                'key' => 'mdr_percentage',
                'frequency_type' => 'weekly',
                'is_percentage' => true
            ],
            [
                'name' => 'Payout Fee',
                'key' => 'payout_fee',
                'frequency_type' => 'weekly',
                'is_percentage' => false
            ],
            [

                'name' => 'Transaction Fee',
                'key' => 'transaction_fee',
                'frequency_type' => 'transaction',
                'is_percentage' => false
            ],
            [
                'name' => 'Weekly Service Fee',
                'key' => 'weekly_service_fee',
                'frequency_type' => 'weekly',
                'is_percentage' => true
            ],
            [
                'name' => 'Monthly Service Fee',
                'key' => 'monthly_service_fee',
                'frequency_type' => 'monthly',
                'is_percentage' => false
            ],
            [
                'name' => 'Annual Membership Fee',
                'key' => 'annual_membership_fee',
                'frequency_type' => 'yearly',
                'is_percentage' => false
            ],
            [
                'name' => 'Chargeback Fee',
                'key' => 'chargeback_fee',
                'frequency_type' => 'transaction',
                'is_percentage' => false
            ]
        ];

        foreach ($feeTypes as $feeType) {
            FeeType::query()->firstOrCreate(
                ['key' => $feeType['key']],
                $feeType
            );
        }
    }
}
