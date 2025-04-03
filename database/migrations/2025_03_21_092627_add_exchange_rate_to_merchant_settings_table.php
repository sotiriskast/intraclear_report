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
        Schema::table('merchant_settings', function (Blueprint $table) {
            $table->decimal('exchange_rate_markup', 6, 4)
                ->default(1.01)
                ->comment('Exchange rate markup factor (e.g., 1.01 for 1%)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('merchant_settings', function (Blueprint $table) {
            $table->dropColumn('exchange_rate_markup');
        });
    }
};
