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
        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id'); // External shop ID from payment gateway
            $table->unsignedBigInteger('merchant_id'); // Reference to merchant
            $table->string('owner_name')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->foreign('merchant_id')->references('id')->on('merchants');
            $table->unique(['shop_id', 'merchant_id']);
            $table->index(['merchant_id', 'active']);
        });

        // Create shop settings table
        Schema::create('shop_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id')->references('id')->on('shops');

            // Rolling Reserve Settings
            $table->integer('rolling_reserve_percentage')->default(1000)->comment('e.g., 1000 for 10%');
            $table->integer('holding_period_days')->default(180)->comment('e.g., 180 for 6 months');

            // Fee Settings
            $table->integer('mdr_percentage')->default(500)->comment('e.g., 500 for 5%');
            $table->integer('transaction_fee')->default(35)->comment('e.g., 35 cents per transaction');
            $table->integer('declined_fee')->default(25)->comment('e.g., 25 cents per transaction');
            $table->integer('payout_fee')->default(100)->comment('e.g., 1.00 EUR');
            $table->integer('refund_fee')->default(100)->comment('e.g., 1.00 EUR');
            $table->integer('chargeback_fee')->default(4000)->comment('e.g., 40.00 EUR');
            $table->integer('monthly_fee')->default(15000)->comment('e.g., 150.00 EUR');
            $table->integer('mastercard_high_risk_fee_applied')->default(15000)->comment('e.g., 150.00 EUR');
            $table->integer('visa_high_risk_fee_applied')->default(15000)->comment('e.g., 150.00 EUR');

            // One-time Fees
            $table->integer('setup_fee')->default(50000)->comment('e.g., 500.00 EUR');
            $table->boolean('setup_fee_charged')->default(false);

            // Exchange rate settings
            $table->decimal('exchange_rate_markup', 6, 4)->default(1.01)->comment('Exchange rate markup factor (e.g., 1.01 for 1%)');
            $table->integer('fx_rate_markup')->default(0)->comment('Exchange rate fee markup (e.g., 100 for 1%)');

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('shop_id')->references('id')->on('shops');
            $table->index(['shop_id']);
        });

        // Create shop fees table (for custom fees per shop)
        Schema::create('shop_fees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id')->references('id')->on('shops');
            $table->unsignedBigInteger('fee_type_id');
            $table->bigInteger('amount');
            $table->timestamp('effective_from')->useCurrent();
            $table->timestamp('effective_to')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('shop_id')->references('id')->on('shops');
            $table->foreign('fee_type_id')->references('id')->on('fee_types');
            $table->index(['shop_id', 'fee_type_id', 'active']);
        });

        // Update rolling_reserve_entries to reference shop instead of merchant
        Schema::table('rolling_reserve_entries', function (Blueprint $table) {
            $table->unsignedBigInteger('shop_id')->nullable()->after('merchant_id');
            $table->foreign('shop_id')->references('id')->on('shops');
            $table->index(['shop_id', 'status', 'release_due_date'], 'idx_shop_reserve_status');
        });

        // Update fee_histories to reference shop instead of just merchant
        Schema::table('fee_histories', function (Blueprint $table) {
            $table->unsignedBigInteger('shop_id')->nullable()->after('merchant_id');
            $table->foreign('shop_id')->references('id')->on('shops');
            $table->index(['shop_id', 'fee_type_id', 'applied_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fee_histories', function (Blueprint $table) {
            $table->dropForeign(['shop_id']);
            $table->dropIndex(['shop_id', 'fee_type_id', 'applied_date']);
            $table->dropColumn('shop_id');
        });

        Schema::table('rolling_reserve_entries', function (Blueprint $table) {
            $table->dropForeign(['shop_id']);
            $table->dropIndex('idx_shop_reserve_status');
            $table->dropColumn('shop_id');
        });

        Schema::dropIfExists('shop_fees');
        Schema::dropIfExists('shop_settings');
        Schema::dropIfExists('shops');
    }
};
