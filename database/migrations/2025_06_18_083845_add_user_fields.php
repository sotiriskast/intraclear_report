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
        Schema::table('users', function (Blueprint $table) {
            $table->string('user_type')->default('admin')->after('email')->comment('Type of user: admin, super-admin, merchant');
            $table->unsignedBigInteger('merchant_id')->nullable()->after('user_type')->comment('Reference to merchant if user_type is merchant');

            $table->foreign('merchant_id')->references('id')->on('merchants')->onDelete('cascade');
            $table->index(['user_type', 'merchant_id']);

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['merchant_id']);
            $table->dropIndex(['user_type', 'merchant_id']);
            $table->dropColumn(['user_type', 'merchant_id']);

        });
    }
};
