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
        Schema::create('gl_journals', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('journal_no')->unique();
            $table->date('journal_date');
            $table->char('currency', 3)->default(config('accounting.base_currency'));
            $table->decimal('fx_rate', 10, 6)->default(1.0);
            $table->string('source')->default('MANUAL');
            $table->string('reference')->nullable();
            $table->text('memo')->nullable();
            $table->enum('status', ['DRAFT', 'POSTED', 'VOIDED', 'REVERSED'])->default('DRAFT');
            $table->timestamp('posted_at')->nullable();
            $table->uuid('posted_by')->nullable();
            $table->uuid('branch_id')->nullable();
            $table->string('external_ref')->nullable();
            $table->timestamps();

            // Indexes
            $table->unique('journal_no');
            $table->index(['status', 'journal_date']);
            $table->index(['source', 'reference']);

            // Foreign keys
            $table->foreign('posted_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gl_journals');
    }
};
