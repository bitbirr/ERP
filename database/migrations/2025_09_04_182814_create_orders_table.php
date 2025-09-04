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
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('branch_id');
            $table->string('order_number');
            $table->string('status')->default('pending');
            $table->uuid('customer_id')->nullable();
            $table->string('currency', 3);
            $table->decimal('subtotal', 15, 2);
            $table->decimal('tax_total', 15, 2)->default(0);
            $table->decimal('discount_total', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2);
            $table->text('notes')->nullable();
            $table->uuid('created_by');
            $table->uuid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->uuid('cancelled_by')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->foreign('branch_id')->references('id')->on('branches');
            $table->foreign('customer_id')->references('id')->on('customers');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('approved_by')->references('id')->on('users');
            $table->foreign('cancelled_by')->references('id')->on('users');

            $table->index(['branch_id', 'status']);
            $table->index(['customer_id']);
            $table->index(['order_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
