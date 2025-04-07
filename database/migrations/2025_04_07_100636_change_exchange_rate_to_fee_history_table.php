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
        Schema::table('fee_histories', function (Blueprint $table) {
            $table->decimal('exchange_rate', 10, 4)
                ->default(0)
                ->change();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fee_histories', function (Blueprint $table) {
            $table->dropColumn('exchange_rate');
        });
    }
};
