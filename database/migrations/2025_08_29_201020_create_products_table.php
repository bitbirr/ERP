<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Enable pgcrypto for gen_random_uuid() if not already enabled
        DB::statement('CREATE EXTENSION IF NOT EXISTS "pgcrypto";');

        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('code')->unique();
            $table->string('name')->index();
            $table->string('type')->index(); // e.g., YIMULU, VOUCHER, EVD, SIM, TELEBIRR, E_AIRTIME
            $table->string('uom')->default('PCS');
            $table->decimal('price', 16, 3)->nullable(); // Selling price (nullable for dynamic)
            $table->decimal('cost', 16, 3)->nullable(); // Purchase cost (nullable)
            $table->decimal('discount_percent', 5, 2)->nullable(); // For VOUCHER/EVD
            $table->string('pricing_strategy')->nullable(); // e.g., FIXED, DISCOUNT, EXACT, MARKUP
            $table->boolean('is_active')->default(true);
            $table->jsonb('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('products');
    }
};