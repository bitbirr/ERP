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
        // pgcrypto for gen_random_uuid()
        DB::unprepared("CREATE EXTENSION IF NOT EXISTS pgcrypto;");

        // tx_type enum
        DB::unprepared(<<<'SQL'
        DO $$
        BEGIN
            IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'tx_type') THEN
                CREATE TYPE tx_type AS ENUM (
                    'ISSUE','REPAY','LOAN','TOPUP','SALE','TRANSFER','ADJUST'
                );
            END IF;
        END$$;
        SQL);

        // channel enum
        DB::unprepared(<<<'SQL'
        DO $$
        BEGIN
            IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'channel') THEN
                CREATE TYPE channel AS ENUM (
                    'WEB','MOBILE','USSD','AGENT','ADMIN'
                );
            END IF;
        END$$;
        SQL);
    
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
         // Usually don't drop enums in prod, but safe here:
        DB::unprepared("DROP TYPE IF EXISTS tx_type;");
        DB::unprepared("DROP TYPE IF EXISTS channel;");
        DB::unprepared("DROP EXTENSION IF EXISTS pgcrypto;");
    }
};
