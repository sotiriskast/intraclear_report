<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create('merchant_portal_preferences', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('merchant_id');
            $table->json('dashboard_widgets')->nullable();
            $table->json('notification_settings')->nullable();
            $table->string('timezone')->default('UTC');
            $table->string('date_format')->default('Y-m-d');
            $table->timestamps();

            $table->foreign('merchant_id')->references('id')->on('merchants')->onDelete('cascade');
            $table->unique('merchant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists('merchant_portal_preferences');
    }
};
