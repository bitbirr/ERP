<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateReceiptsTable extends Migration
{
    public function up()
    {
        Schema::create('receipts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('branch_id');
            $table->string('number');
            $table->enum('status', ['DRAFT', 'POSTED', 'VOIDED', 'REFUNDED'])->default('DRAFT');
            $table->uuid('customer_id')->nullable();
            $table->char('currency', 3)->default('ETB');
            $table->decimal('subtotal', 16, 2)->default(0);
            $table->decimal('tax_total', 16, 2)->default(0);
            $table->decimal('discount_total', 16, 2)->default(0);
            $table->decimal('grand_total', 16, 2)->default(0);
            $table->decimal('paid_total', 16, 2)->default(0);
            $table->enum('payment_method', ['CASH', 'CARD', 'MOBILE', 'TRANSFER', 'MIXED'])->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('posted_by')->nullable();
            $table->uuid('voided_by')->nullable();
            $table->uuid('refunded_by')->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'number']);
            $table->foreign('branch_id')->references('id')->on('branches');
            // Check constraints removed for compatibility
        });
    }

    public function down()
    {
        Schema::dropIfExists('receipts');
    }
}
