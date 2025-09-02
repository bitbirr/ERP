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
        Schema::create('customers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type')->default('individual'); // individual or organization
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('tax_id')->nullable(); // TIN for Ethiopia
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable(); // additional custom fields
            $table->timestamps();

            $table->index(['type', 'is_active']);
            $table->index('email');
            $table->index('phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};