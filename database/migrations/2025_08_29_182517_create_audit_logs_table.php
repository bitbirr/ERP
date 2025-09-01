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
          Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('actor_id')->nullable();
            $table->string('actor_ip')->nullable();
            $table->string('actor_user_agent')->nullable();
            $table->string('action'); // e.g., tx.created, user.login
            $table->string('subject_type')->nullable();
            $table->string('subject_id')->nullable();
            $table->jsonb('changes_old')->nullable();
            $table->jsonb('changes_new')->nullable();
            $table->jsonb('context')->nullable(); // route, method, branch_id, request_id, etc.
            $table->timestamp('created_at')->useCurrent();

            $table->index('action');
            $table->index(['subject_type','subject_id']);
            $table->index(['actor_id','created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
