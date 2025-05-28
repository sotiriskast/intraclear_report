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
        Schema::create('decta_transactions', function (Blueprint $table) {
            $table->id();

            // File relationship
            $table->foreignId('decta_file_id')->constrained('decta_files')->onDelete('cascade');

            // Gateway matching data
            $table->unsignedBigInteger('gateway_account_id')->nullable()->index();
            $table->unsignedBigInteger('gateway_shop_id')->nullable()->index();
            $table->string('gateway_transaction_id')->nullable()->index();
            $table->string('gateway_trx_id')->nullable()->index();
            $table->string('gateway_transaction_date')->nullable();
            $table->string('gateway_bank_response_date')->nullable();
            $table->string('gateway_transaction_status')->nullable();


            // Core transaction data from Decta CSV
            $table->string('payment_id')->index();
            $table->string('card')->nullable();
            $table->string('merchant_name')->nullable();
            $table->string('merchant_id')->nullable();
            $table->string('terminal_id')->nullable();
            $table->string('card_type_name')->nullable();
            $table->string('acq_ref_nr')->nullable();
            $table->string('tr_batch_id')->nullable();
            $table->timestamp('tr_batch_open_date')->nullable();
            $table->timestamp('tr_date_time')->nullable()->index();
            $table->string('tr_type')->nullable();
            $table->bigInteger('tr_amount')->nullable(); // Amount in cents
            $table->string('tr_ccy', 3)->nullable();
            $table->string('msc')->nullable();
            $table->string('tr_ret_ref_nr')->nullable();
            $table->string('tr_approval_id')->nullable();
            $table->timestamp('tr_processing_date')->nullable();
            $table->string('merchant_iban_code')->nullable();
            $table->string('proc_code')->nullable();
            $table->string('issuer_country')->nullable();
            $table->string('proc_region')->nullable();
            $table->string('mcc')->nullable();
            $table->string('merchant_country')->nullable();
            $table->string('tran_region')->nullable();
            $table->string('card_product_type')->nullable();
            $table->string('user_define_field1')->nullable();
            $table->string('user_define_field2')->nullable();
            $table->string('user_define_field3')->nullable();
            $table->string('merchant_legal_name')->nullable();
            $table->string('card_product_class')->nullable();
            $table->string('eci_sli')->nullable();
            $table->string('sca_exemption')->nullable();
            $table->string('point_code')->nullable();
            $table->string('pos_env_indicator')->nullable();
            $table->string('par')->nullable();



            // Matching status
            $table->boolean('is_matched')->default(false)->index();
            $table->timestamp('matched_at')->nullable();
            $table->string('status')->default('pending')->index();
            $table->text('error_message')->nullable();
            $table->json('matching_attempts')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes for common queries
            $table->index(['decta_file_id', 'status']);
            $table->index(['is_matched', 'status']);
            $table->index(['tr_amount', 'tr_ccy']);
            $table->index(['tr_approval_id', 'tr_ret_ref_nr']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('decta_transactions');
    }
};
