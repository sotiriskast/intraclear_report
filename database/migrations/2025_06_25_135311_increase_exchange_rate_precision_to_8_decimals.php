<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     * Increase exchange rate precision from 4 to 8 decimal places
     */
    public function up(): void
    {
        // Update chargeback_trackings table - exchange_rate field
        Schema::table('chargeback_trackings', function (Blueprint $table) {
            $table->decimal('exchange_rate', 18, 8)
                ->comment('Exchange rate with 8 decimal precision for accurate conversions')
                ->change();
        });

        // Update fee_histories table - exchange_rate field
        Schema::table('fee_histories', function (Blueprint $table) {
            $table->decimal('exchange_rate', 18, 8)
                ->default(0)
                ->comment('Exchange rate with 8 decimal precision for accurate conversions')
                ->change();
        });
        // Update rolling_reserve_entries table - exchange_rate field
        Schema::table('rolling_reserve_entries', function (Blueprint $table) {
            $table->decimal('exchange_rate', 18, 8)
                ->default(0)
                ->comment('Exchange rate with 8 decimal precision for accurate conversions')
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     * Revert exchange rate precision back to 4 decimal places
     */
    public function down(): void
    {
        // Revert chargeback_trackings table
        Schema::table('chargeback_trackings', function (Blueprint $table) {
            $table->decimal('exchange_rate', 10, 4)->change();
        });
        // Revert fee_histories table
        Schema::table('rolling_reserve_entries', function (Blueprint $table) {
            $table->decimal('exchange_rate', 18, 4)->default(0)->change();
        });
        // Revert fee_histories table
        Schema::table('fee_histories', function (Blueprint $table) {
            $table->decimal('exchange_rate', 10, 4)->default(0)->change();
        });
    }
};
