<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Fix chargeback_trackings table
        Schema::table('chargeback_trackings', function (Blueprint $table) {
            $table->bigInteger('amount')->change();
            $table->bigInteger('amount_eur')->change();
        });

        // Fix rolling_reserve_entries table (if not already fixed)
        if (!$this->isColumnBigInteger('rolling_reserve_entries', 'original_amount')) {
            Schema::table('rolling_reserve_entries', function (Blueprint $table) {
                $table->bigInteger('original_amount')->change();
                $table->bigInteger('reserve_amount_eur')->change();
            });
        }

        // Fix fee_histories table (if not already fixed)
        if (!$this->isColumnBigInteger('fee_histories', 'base_amount')) {
            Schema::table('fee_histories', function (Blueprint $table) {
                $table->bigInteger('base_amount')->change();
                $table->bigInteger('fee_amount_eur')->change();
            });
        }

        // Fix merchant_fees table (if not already fixed)
        if (!$this->isColumnBigInteger('merchant_fees', 'amount')) {
            Schema::table('merchant_fees', function (Blueprint $table) {
                $table->bigInteger('amount')->change();
            });
        }

        // Fix shop_fees table (if exists)
        if (Schema::hasTable('shop_fees')) {
            Schema::table('shop_fees', function (Blueprint $table) {
                $table->bigInteger('amount')->change();
            });
        }

        // Fix any other monetary fields that might exist
        $this->fixAdditionalMonetaryFields();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Note: Be careful when reversing as data might be lost
        // Only reverse if you're sure the data will fit in the smaller field

        Schema::table('chargeback_trackings', function (Blueprint $table) {
            $table->decimal('amount', 15, 2)->change();
            $table->decimal('amount_eur', 15, 2)->change();
        });

        Schema::table('rolling_reserve_entries', function (Blueprint $table) {
            $table->decimal('original_amount', 15, 2)->change();
            $table->decimal('reserve_amount_eur', 15, 2)->change();
        });

        Schema::table('fee_histories', function (Blueprint $table) {
            $table->decimal('base_amount', 15, 2)->change();
            $table->decimal('fee_amount_eur', 15, 2)->change();
        });

        Schema::table('merchant_fees', function (Blueprint $table) {
            $table->decimal('amount', 15, 2)->change();
        });

        if (Schema::hasTable('shop_fees')) {
            Schema::table('shop_fees', function (Blueprint $table) {
                $table->decimal('amount', 15, 2)->change();
            });
        }
    }

    /**
     * Check if a column is already a bigInteger
     */
    private function isColumnBigInteger(string $tableName, string $columnName): bool
    {
        try {
            $columnType = DB::connection()->getDoctrineColumn($tableName, $columnName)->getType()->getName();
            return in_array(strtolower($columnType), ['bigint', 'integer']);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Fix any additional monetary fields that might exist
     */
    private function fixAdditionalMonetaryFields(): void
    {
        // Check for any other tables with monetary fields that need fixing
        $monetaryFields = [
            'merchant_settings' => [
                'transaction_fee', 'declined_fee', 'payout_fee', 'refund_fee',
                'chargeback_fee', 'monthly_fee', 'mastercard_high_risk_fee_applied',
                'visa_high_risk_fee_applied', 'setup_fee', 'rolling_reserve_percentage',
                'mdr_percentage', 'fx_rate_markup'
            ],
            'shop_settings' => [
                'transaction_fee', 'declined_fee', 'payout_fee', 'refund_fee',
                'chargeback_fee', 'monthly_fee', 'mastercard_high_risk_fee_applied',
                'visa_high_risk_fee_applied', 'setup_fee', 'rolling_reserve_percentage',
                'mdr_percentage', 'fx_rate_markup'
            ]
        ];

        foreach ($monetaryFields as $tableName => $fields) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) use ($fields) {
                    foreach ($fields as $field) {
                        if (Schema::hasColumn($table->getTable(), $field)) {
                            $table->bigInteger($field)->change();
                        }
                    }
                });
            }
        }
    }
};
