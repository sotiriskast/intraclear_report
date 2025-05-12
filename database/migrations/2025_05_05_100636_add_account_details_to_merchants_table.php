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
        Schema::table('merchants', function (Blueprint $table) {
            $table->string('legal_name')->nullable()->comment('Legal Name of the merchant');
            $table->string('register_country')->nullable()->comment('Register Country of the merchant');
            $table->char('iso_country_code', 2)->nullable()->comment('ISO Country CODE of the merchant. E.g. CY');
            $table->string('city')->nullable()->comment('Register City of the merchant');
            $table->string('street')->nullable()->comment('Register Street of the merchant');
            $table->string('postcode')->nullable()->comment('Register postcode of the merchant');
            $table->string('vat')->nullable()->comment('Register VAT/TIC of the merchant');
            $table->string('iban')->nullable()->comment('Iban of the merchant');
            $table->string('mcc1')->nullable()->comment('Register MCC1 of the merchant');
            $table->string('mcc2')->nullable()->comment('Register MCC2 of the merchant');
            $table->string('mcc3')->nullable()->comment('Register MCC3 of the merchant');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->dropColumn('legal_name');
            $table->dropColumn('register_country');
            $table->dropColumn('city');
            $table->dropColumn('street');
            $table->dropColumn('postcode');
            $table->dropColumn('vat');
            $table->dropColumn('mcc1');
            $table->dropColumn('mcc2');
            $table->dropColumn('mcc3');
        });
    }
};
