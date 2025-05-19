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
        Schema::table('chargeback_trackings', function (Blueprint $table) {
            $table->unsignedBigInteger('shop_id')->nullable()->after('merchant_id');
            $table->foreign('shop_id')->references('id')->on('shops');
            $table->index(['shop_id', 'transaction_id']);
            $table->index(['shop_id', 'current_status', 'settled']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chargeback_trackings', function (Blueprint $table) {
            $table->dropForeign(['shop_id']);
            $table->dropIndex(['shop_id', 'transaction_id']);
            $table->dropIndex(['shop_id', 'current_status', 'settled']);
            $table->dropColumn('shop_id');
        });
    }
};
