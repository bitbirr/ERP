<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('product_id');
            $table->uuid('branch_id');
            $table->decimal('qty', 16, 3);
            $table->enum('type', [
                'OPENING',
                'RECEIVE',
                'ISSUE',
                'RESERVE',
                'UNRESERVE',
                'TRANSFER_OUT',
                'TRANSFER_IN',
                'ADJUST'
            ])->comment('stock_move_type');
            $table->string('ref')->nullable();
            $table->jsonb('meta')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'branch_id', 'created_at']);
            $table->index('type');
            $table->index('ref');
            $table->index('created_at');

            $table->foreign('product_id')->references('id')->on('products');
            $table->foreign('branch_id')->references('id')->on('branches');
            $table->foreign('created_by')->references('id')->on('users');
        });

        // Add CHECK constraint for qty != 0 (allow negative for issues/adjusts)
        DB::statement('ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_qty_not_zero CHECK (qty != 0);');
    }

    public function down()
    {
        Schema::dropIfExists('stock_movements');
    }
};