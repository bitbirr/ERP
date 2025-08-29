<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
         Schema::create('roles', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        Schema::create('capabilities', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('name')->unique();
            $table->string('key')->unique(); // e.g., tx.view
            $table->string('group')->nullable(); // e.g., transactions
            $table->timestamps();
        });

        Schema::create('role_capability', function (Blueprint $table) {
            $table->uuid('role_id');
            $table->uuid('capability_id');
            $table->primary(['role_id', 'capability_id']);
            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
            $table->foreign('capability_id')->references('id')->on('capabilities')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
     Schema::dropIfExists('role_capability');
        Schema::dropIfExists('capabilities');
        Schema::dropIfExists('roles');
    }
};
