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
        // Function to validate journal balance
        DB::unprepared('
            CREATE OR REPLACE FUNCTION validate_journal_balance()
            RETURNS TRIGGER AS $$
            BEGIN
                -- Only validate if status is being set to POSTED
                IF NEW.status = \'POSTED\' THEN
                    -- Check if debits equal credits
                    IF NOT EXISTS (
                        SELECT 1 FROM gl_lines
                        WHERE journal_id = NEW.id
                        GROUP BY journal_id
                        HAVING SUM(debit) = SUM(credit)
                    ) THEN
                        RAISE EXCEPTION \'Journal debits (%) must equal credits (%) for journal %\', (SELECT SUM(debit) FROM gl_lines WHERE journal_id = NEW.id), (SELECT SUM(credit) FROM gl_lines WHERE journal_id = NEW.id), NEW.journal_no;
                    END IF;
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ');

        // Function to prevent edits to posted journals
        DB::unprepared('
            CREATE OR REPLACE FUNCTION prevent_posted_journal_edits()
            RETURNS TRIGGER AS $$
            BEGIN
                -- Check if the journal is already posted
                IF EXISTS (SELECT 1 FROM gl_journals WHERE id = OLD.journal_id AND status IN (\'POSTED\', \'VOIDED\', \'REVERSED\')) THEN
                    RAISE EXCEPTION \'Cannot modify lines of a posted, voided, or reversed journal\';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ');

        // Function to auto-assign line numbers
        DB::unprepared('
            CREATE OR REPLACE FUNCTION auto_assign_line_no()
            RETURNS TRIGGER AS $$
            BEGIN
                IF NEW.line_no IS NULL THEN
                    SELECT COALESCE(MAX(line_no), 0) + 1
                    INTO NEW.line_no
                    FROM gl_lines
                    WHERE journal_id = NEW.journal_id;
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ');

        // Create triggers
        DB::unprepared('CREATE TRIGGER validate_journal_balance_trigger BEFORE UPDATE ON gl_journals FOR EACH ROW EXECUTE FUNCTION validate_journal_balance();');
        DB::unprepared('CREATE TRIGGER prevent_posted_journal_edits_trigger BEFORE UPDATE OR DELETE ON gl_lines FOR EACH ROW EXECUTE FUNCTION prevent_posted_journal_edits();');
        DB::unprepared('CREATE TRIGGER auto_assign_line_no_trigger BEFORE INSERT ON gl_lines FOR EACH ROW EXECUTE FUNCTION auto_assign_line_no();');

        // Optional: Create gl_account_balances table for running balances
        Schema::create('gl_account_balances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('account_id');
            $table->date('period');
            $table->uuid('branch_id')->nullable();
            $table->decimal('debit', 18, 2)->default(0);
            $table->decimal('credit', 18, 2)->default(0);
            $table->decimal('net', 18, 2)->default(0);
            $table->timestamps();

            $table->unique(['account_id', 'period', 'branch_id']);
            $table->index(['account_id', 'period']);
            $table->foreign('account_id')->references('id')->on('gl_accounts')->onDelete('cascade');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop triggers
        DB::unprepared('DROP TRIGGER IF EXISTS validate_journal_balance_trigger ON gl_journals;');
        DB::unprepared('DROP TRIGGER IF EXISTS prevent_posted_journal_edits_trigger ON gl_lines;');
        DB::unprepared('DROP TRIGGER IF EXISTS auto_assign_line_no_trigger ON gl_lines;');

        // Drop functions
        DB::unprepared('DROP FUNCTION IF EXISTS validate_journal_balance();');
        DB::unprepared('DROP FUNCTION IF EXISTS prevent_posted_journal_edits();');
        DB::unprepared('DROP FUNCTION IF EXISTS auto_assign_line_no();');

        // Drop balances table
        Schema::dropIfExists('gl_account_balances');
    }
};
