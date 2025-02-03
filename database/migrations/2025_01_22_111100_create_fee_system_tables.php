<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('merchants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('fee_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('key')->unique();
            $table->string('frequency_type'); // transaction, weekly, monthly, yearly, one_time
            $table->boolean('is_percentage')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        // Merchant Fee Settings
        Schema::create('merchant_fees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('merchant_id');
            $table->unsignedBigInteger('fee_type_id');
            $table->integer('amount'); // Can be percentage or fixed amount
            $table->timestamp('effective_from')->useCurrent();
            $table->timestamp('effective_to')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('fee_type_id')->references('id')->on('fee_types');
        });
        // First, create a comprehensive merchant settings table
        Schema::create('merchant_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('merchant_id');

            // Rolling Reserve Settings
            $table->integer('rolling_reserve_percentage')->default(1000)->comment('e.g., 1000 for 10%');
            $table->integer('holding_period_days')->default(180)->comment('e.g., 180 for 6 months');

            // Fee Settings
            $table->integer('mdr_percentage')->default(500)->comment('e.g., 500 for 5%');
            $table->integer('transaction_fee')->default(35)->comment('e.g., 35 cents per transaction');
            $table->integer('declined_fee')->default(25)->comment('e.g., 25 cents per transaction');
            $table->integer('payout_fee')->default(100)->comment('e.g., 1.00 EUR');
            $table->integer('refund_fee')->default(100)->comment('e.g., 1.00 EUR');
            $table->integer('chargeback_fee')->default(4000)->comment('e.g., 25.00 EUR');
            $table->integer('monthly_fee')->default(15000)->comment('e.g., 150.00 EUR');
            $table->integer('mastercard_high_risk_fee_applied')->default(15000)->comment('e.g., 150.00 EUR');
            $table->integer('visa_high_risk_fee_applied')->default(15000)->comment('e.g., 150.00 EUR');

            // One-time Fees
            $table->integer('setup_fee')->default(50000)->comment('e.g., 500.00 EUR');
            $table->boolean('setup_fee_charged')->default(false);
            // Standard Timestamps and Soft Delete
            $table->timestamps();
            $table->softDeletes();
            // Foreign Key
            $table->foreign('merchant_id')->references('id')->on('merchants');
            // Indexes for common queries
            $table->index(['merchant_id']);
        });
        // Rolling Reserve Transactions
        Schema::create('rolling_reserve_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('merchant_id');
            $table->integer('original_amount');
            $table->string('original_currency', 3);
            $table->integer('reserve_amount_eur');
            $table->decimal('exchange_rate', 10, 4)->nullable();
            $table->date('period_start');        // Settlement period start
            $table->date('period_end');          // Settlement period end
            $table->timestamp('created_at');     // When the reserve was created
            $table->date('release_due_date');    // When the reserve becomes eligible for release
            $table->timestamp('released_at')->nullable();  // When the reserve was actually released
            $table->string('status', 20);        // pending, released, cancelled
            $table->timestamp('updated_at')->nullable();
            $table->softDeletes();

            $table->foreign('merchant_id')->references('id')->on('merchants');
            $table->index(['merchant_id', 'status', 'release_due_date'], 'idx_reserve_status');
            $table->index(['period_start', 'period_end'], 'idx_period');
        });

        // Fee History (for tracking applied fees)
        Schema::create('fee_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('merchant_id');
            $table->unsignedBigInteger('fee_type_id');
            $table->integer('base_amount');
            $table->string('base_currency', 3);
            $table->integer('fee_amount_eur');
            $table->integer('exchange_rate');
            $table->timestamp('applied_date')->useCurrent();
            $table->string('report_reference')->nullable();
            $table->timestamps();

            $table->foreign('fee_type_id')->references('id')->on('fee_types');
        });
        Schema::create('settlement_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('merchant_id');
            $table->string('report_path');
            $table->date('start_date');
            $table->date('end_date');
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('merchant_id')->references('id')->on('merchants');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('merchants');
        Schema::dropIfExists('fee_histories');
        Schema::dropIfExists('rolling_reserve_entries');
        Schema::dropIfExists('merchant_rolling_reserves');
        Schema::dropIfExists('merchant_fees');
        Schema::dropIfExists('fee_types');
        Schema::dropIfExists('settlement_reports');
    }
};
