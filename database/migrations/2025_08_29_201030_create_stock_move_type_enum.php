<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Create enum type stock_move_type if not exists (idempotent)
        DB::statement("
            DO $$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'stock_move_type') THEN
                    CREATE TYPE stock_move_type AS ENUM (
                        'OPENING',
                        'RECEIVE',
                        'ISSUE',
                        'RESERVE',
                        'UNRESERVE',
                        'TRANSFER_OUT',
                        'TRANSFER_IN',
                        'ADJUST'
                    );
                END IF;
            END
            $$;
        ");
    }

    public function down()
    {
        // Drop enum type if exists
        DB::statement("DROP TYPE IF EXISTS stock_move_type;");
    }
};