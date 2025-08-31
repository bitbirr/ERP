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
        Schema::create('telebirr_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->enum('tx_type', ['ISSUE', 'REPAY', 'LOAN', 'TOPUP']);
            $table->uuid('agent_id')->nullable();
            $table->uuid('bank_account_id')->nullable();
            $table->decimal('amount', 18, 2)->check('amount > 0');
            $table->string('currency', 3)->default('ETB');
            $table->string('idempotency_key', 120)->unique();
            $table->uuid('gl_journal_id')->nullable();
            $table->enum('status', ['Draft', 'Posted', 'Voided'])->default('Posted');
            $table->text('remarks')->nullable();
            $table->string('external_ref')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('approved_by')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->index(['tx_type', 'agent_id']);
            $table->index('created_at');

            $table->foreign('agent_id')->references('id')->on('telebirr_agents');
            // $table->foreign('bank_account_id')->references('id')->on('bank_accounts'); // Removed due to migration order
            $table->foreign('gl_journal_id')->references('id')->on('gl_journals');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('approved_by')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telebirr_transactions');
    }
};
