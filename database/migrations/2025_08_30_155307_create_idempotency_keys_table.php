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
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('scope');
            $table->string('key');
            $table->string('request_hash');
            $table->enum('status', ['PENDING', 'SUCCEEDED', 'FAILED'])->default('PENDING');
            $table->jsonb('response_snapshot')->nullable();
            $table->timestamp('locked_until')->nullable();
            $table->timestamps();

            // Indexes
            $table->unique(['scope', 'key']);
            $table->index('locked_until');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
