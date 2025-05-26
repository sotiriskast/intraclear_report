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
        Schema::create('decta_files', function (Blueprint $table) {
            $table->id();
            $table->string('filename');
            $table->string('original_path');
            $table->string('local_path');
            $table->integer('file_size')->nullable();
            $table->string('file_type')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('processed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('filename');
            $table->index('status');
            $table->index('processed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists('decta_files');

    }
};
