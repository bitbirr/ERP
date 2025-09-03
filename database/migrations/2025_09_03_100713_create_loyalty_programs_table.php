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
        Schema::create('loyalty_programs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->decimal('discount_percentage', 5, 2)->default(10.00);
            $table->decimal('max_discount_amount', 10, 2)->nullable();
            $table->decimal('min_purchase_amount', 10, 2)->default(0.00);
            $table->integer('points_per_etb')->default(1);
            $table->integer('points_required_for_discount')->default(100);
            $table->integer('valid_days')->default(30);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loyalty_programs');
    }
};
