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
        Schema::create('customer_segments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('criteria')->nullable(); // dynamic criteria for segmenting
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });

        // Pivot table for many-to-many relationship
        Schema::create('customer_segment_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('customer_id');
            $table->uuid('segment_id');
            $table->timestamps();

            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->foreign('segment_id')->references('id')->on('customer_segments')->onDelete('cascade');
            $table->unique(['customer_id', 'segment_id']);
            $table->index(['customer_id', 'segment_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_segment_assignments');
        Schema::dropIfExists('customer_segments');
    }
};