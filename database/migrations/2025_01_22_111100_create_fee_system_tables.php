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
            $table->string('frequency_type'); // transaction, daily, weekly, monthly, yearly, one_time
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

        // Rolling Reserve Settings
        Schema::create('merchant_rolling_reserves', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('merchant_id');
            $table->integer('percentage'); // e.g., 1000 for 10%
            $table->integer('holding_period_days'); // e.g., 180 for 6 months
            $table->string('currency', 3); // EUR, USD, etc.
            $table->timestamp('effective_from')->useCurrent();
            $table->timestamp('effective_to')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // Rolling Reserve Transactions
        Schema::create('rolling_reserve_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('merchant_id');
            $table->string('transaction_reference');
            $table->integer('original_amount');
            $table->string('original_currency', 3);
            $table->integer('reserve_amount_eur');
            $table->integer('exchange_rate');
            $table->timestamp('transaction_date');
            $table->timestamp('release_date');
            $table->timestamp('released_at')->nullable();
            $table->string('status'); // pending, released, cancelled
            $table->timestamps();
            $table->softDeletes();
        });

        // Fee History (for tracking applied fees)
        Schema::create('fee_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('merchant_id');
            $table->unsignedBigInteger('fee_type_id');
            $table->string('transaction_reference')->nullable();
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
        Schema::dropIfExists('merchant');
        Schema::dropIfExists('fee_history');
        Schema::dropIfExists('rolling_reserve_entries');
        Schema::dropIfExists('merchant_rolling_reserves');
        Schema::dropIfExists('merchant_fees');
        Schema::dropIfExists('fee_types');
        Schema::dropIfExists('settlement_reports');
    }
};
