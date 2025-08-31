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
        Schema::create('telebirr_agents', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('name');
            $table->string('short_code')->unique();
            $table->string('phone');
            $table->string('location')->nullable();
            $table->enum('status', ['Active', 'Dormant', 'Inactive'])->default('Active');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('short_code');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telebirr_agents');
    }
};
