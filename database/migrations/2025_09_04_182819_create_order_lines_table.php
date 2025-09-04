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
        Schema::create('order_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('order_id');
            $table->uuid('product_id');
            $table->string('uom');
            $table->decimal('qty', 10, 2);
            $table->decimal('price', 10, 2);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->nullable();
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('line_total', 10, 2);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products');

            $table->index(['order_id']);
            $table->index(['product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_lines');
    }
};
