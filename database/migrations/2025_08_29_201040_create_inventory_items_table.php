<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('product_id');
            $table->uuid('branch_id');
            $table->decimal('on_hand', 16, 3)->default(0);
            $table->decimal('reserved', 16, 3)->default(0);
            // available: generated column (PG ≥ 12)
            $table->decimal('available', 16, 3)
                ->storedAs('on_hand - reserved');
            $table->timestamps();

            $table->unique(['product_id', 'branch_id']);
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');
        });

        // Add CHECK constraints
        DB::statement('ALTER TABLE inventory_items ADD CONSTRAINT inventory_items_on_hand_nonneg CHECK (on_hand >= 0);');
        DB::statement('ALTER TABLE inventory_items ADD CONSTRAINT inventory_items_reserved_nonneg CHECK (reserved >= 0);');
        DB::statement('ALTER TABLE inventory_items ADD CONSTRAINT inventory_items_reserved_le_on_hand CHECK (reserved <= on_hand);');
    }

    public function down()
    {
        Schema::dropIfExists('inventory_items');
    }
};