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
        if (!Schema::hasTable('capabilities')) {
            Schema::create('capabilities', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('key')->unique();   // consider unique
                $table->string('name');
                $table->string('group_name')->nullable(); // rename from "group"
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('capabilities');
    }
};
