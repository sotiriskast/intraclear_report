<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop merchant_fees first (in case it has FK to merchant_settings)
        Schema::dropIfExists('merchant_fees');

        // Then drop merchant_settings
        Schema::dropIfExists('merchant_settings');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreate merchant_settings first
        Schema::create('merchant_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('merchant_id');

            // Rolling Reserve Settings
            $table->bigInteger('rolling_reserve_percentage')->default(1000)->comment('e.g., 1000 for 10%');
            $table->integer('holding_period_days')->default(180)->comment('e.g., 180 for 6 months');

            // Fee Settings
            $table->bigInteger('mdr_percentage')->default(500)->comment('e.g., 500 for 5%');
            $table->bigInteger('transaction_fee')->default(35)->comment('e.g., 35 cents per transaction');
            $table->bigInteger('declined_fee')->default(25)->comment('e.g., 25 cents per transaction');
            $table->bigInteger('payout_fee')->default(100)->comment('e.g., 1.00 EUR');
            $table->bigInteger('refund_fee')->default(100)->comment('e.g., 1.00 EUR');
            $table->bigInteger('chargeback_fee')->default(4000)->comment('e.g., 40.00 EUR');
            $table->bigInteger('monthly_fee')->default(15000)->comment('e.g., 150.00 EUR');
            $table->bigInteger('mastercard_high_risk_fee_applied')->default(15000)->comment('e.g., 150.00 EUR');
            $table->bigInteger('visa_high_risk_fee_applied')->default(15000)->comment('e.g., 150.00 EUR');

            // One-time Fees
            $table->bigInteger('setup_fee')->default(50000)->comment('e.g., 500.00 EUR');
            $table->boolean('setup_fee_charged')->default(false);

            // Exchange rate settings
            $table->decimal('exchange_rate_markup', 6, 4)->default(1.01)->comment('Exchange rate markup factor (e.g., 1.01 for 1%)');
            $table->bigInteger('fx_rate_markup')->default(0)->comment('Exchange rate fee markup (e.g., 100 for 1%)');

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('merchant_id')->references('id')->on('merchants');
            $table->index(['merchant_id']);
        });

        // Then recreate merchant_fees
        Schema::create('merchant_fees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('merchant_id');
            $table->string('fee_type'); // e.g., 'transaction', 'refund', 'chargeback', etc.
            $table->bigInteger('amount'); // Amount in cents
            $table->string('currency', 3)->default('EUR');
            $table->string('description')->nullable();
            $table->date('applied_date');
            $table->boolean('is_processed')->default(false);
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('merchant_id')->references('id')->on('merchants');
            $table->index(['merchant_id', 'fee_type']);
            $table->index(['applied_date']);
            $table->index(['is_processed']);
        });
    }
};
