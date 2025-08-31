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
        Schema::create('gl_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('code')->unique();
            $table->string('name');
            $table->enum('type', ['ASSET', 'LIABILITY', 'EQUITY', 'REVENUE', 'EXPENSE', 'CONTRA_ASSET', 'CONTRA_LIABILITY', 'CONTRA_EQUITY', 'CONTRA_REVENUE', 'CONTRA_EXPENSE']);
            $table->enum('normal_balance', ['DEBIT', 'CREDIT']);
            $table->uuid('parent_id')->nullable();
            $table->smallInteger('level')->default(1);
            $table->boolean('is_postable')->default(true);
            $table->enum('status', ['ACTIVE', 'ARCHIVED'])->default('ACTIVE');
            $table->uuid('branch_id')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('parent_id');
            $table->index(['type', 'normal_balance']);

            // Foreign key to branches (not self-referencing)
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('set null');
        });

        // Add self-referencing foreign key after table creation
        Schema::table('gl_accounts', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('gl_accounts')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gl_accounts');
    }
};
