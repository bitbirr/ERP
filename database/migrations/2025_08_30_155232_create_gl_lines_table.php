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
        Schema::create('gl_lines', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('journal_id');
            $table->integer('line_no');
            $table->uuid('account_id');
            // Dimensions
            $table->uuid('branch_id')->nullable();
            $table->uuid('cost_center_id')->nullable();
            $table->uuid('project_id')->nullable();
            $table->uuid('customer_id')->nullable();
            $table->uuid('supplier_id')->nullable();
            $table->uuid('item_id')->nullable();
            $table->text('memo')->nullable();
            // Amounts
            $table->decimal('debit', 18, 2)->default(0);
            $table->decimal('credit', 18, 2)->default(0);
            $table->timestamps();

            // Indexes
            $table->index(['journal_id', 'line_no']);
            $table->index('account_id');

            // Foreign keys
            $table->foreign('journal_id')->references('id')->on('gl_journals')->onDelete('cascade');
            $table->foreign('account_id')->references('id')->on('gl_accounts')->onDelete('restrict');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('set null');
            // Note: Other dimension foreign keys would need their respective tables

        });

        // Add CHECK constraints
        DB::statement('ALTER TABLE gl_lines ADD CONSTRAINT gl_lines_debit_credit_non_negative CHECK (debit >= 0 AND credit >= 0);');
        DB::statement('ALTER TABLE gl_lines ADD CONSTRAINT gl_lines_debit_or_credit CHECK (((debit > 0)::int + (credit > 0)::int) = 1);');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gl_lines');
    }
};
