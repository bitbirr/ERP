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
       Schema::create('user_policies', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('user_id');
            $table->uuid('branch_id')->nullable(); // null = global
            $table->string('capability_key'); // e.g., tx.view
            $table->boolean('granted')->default(true);
            $table->timestamps();

            $table->unique(['user_id','branch_id','capability_key']);
            $table->index(['user_id','capability_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_policies');
    }
};
