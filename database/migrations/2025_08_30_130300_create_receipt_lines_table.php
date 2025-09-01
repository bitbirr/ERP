<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateReceiptLinesTable extends Migration
{
    public function up()
    {
        Schema::create('receipt_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('receipt_id');
            $table->uuid('product_id');
            $table->string('uom', 10)->default('PCS');
            $table->decimal('qty', 16, 3)->check('qty > 0');
            $table->decimal('price', 16, 2)->check('price >= 0');
            $table->decimal('discount', 16, 2)->default(0)->check('discount >= 0');
            $table->decimal('tax_rate', 6, 3)->default(0);
            $table->decimal('tax_amount', 16, 2)->default(0);
            $table->decimal('line_total', 16, 2)->default(0);
            $table->jsonb('meta')->nullable();
            $table->timestamps();

            $table->foreign('receipt_id')->references('id')->on('receipts')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products');
            $table->index('receipt_id');
            $table->index('product_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('receipt_lines');
    }
}
