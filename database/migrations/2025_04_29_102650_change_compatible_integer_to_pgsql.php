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
        Schema::table('fee_histories', function (Blueprint $table) {
            $table->bigInteger('base_amount')->change();
            $table->bigInteger('fee_amount_eur')->change();
        });
        Schema::table('merchant_fees', function (Blueprint $table) {
            $table->bigInteger('amount')->change();
        });
        Schema::table('rolling_reserve_entries', function (Blueprint $table) {
            $table->bigInteger('original_amount')->change();
            $table->bigInteger('reserve_amount_eur')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fee_histories', function (Blueprint $table) {
            $table->dropColumn('base_amount');
        });
        Schema::table('merchant_fees', function (Blueprint $table) {
            $table->dropColumn('amount');
        });
        Schema::table('rolling_reserve_entries', function (Blueprint $table) {
            $table->dropColumn('original_amount');
            $table->dropColumn('reserve_amount_eur');
        });
    }
};
