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
       Schema::create('transactions', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->decimal('amount', 16, 2);
            $table->enum('tx_type', ['ISSUE','REPAY','LOAN','TOPUP','SALE','TRANSFER','ADJUST'])->nullable(false);
            $table->enum('channel', ['WEB','MOBILE','USSD','AGENT','ADMIN'])->nullable(false);
            $table->jsonb('meta')->nullable();

            // If your users table uses bigints, use unsignedBigInteger and FK instead:
            // $table->uuid('user_id')->nullable();
            // $table->foreign('user_id')->references('id')->on('users');

            $table->timestamps();
            $table->index(['tx_type', 'channel']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
       Schema::dropIfExists('transactions');
    }
};
