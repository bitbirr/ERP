<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

class CreateReceiptEnums extends Migration
{
    public function up()
    {
        // Create receipt_status enum if not exists
        DB::statement("
            DO $$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'receipt_status') THEN
                    CREATE TYPE receipt_status AS ENUM ('DRAFT', 'POSTED', 'VOIDED', 'REFUNDED');
                END IF;
            END
            $$;
        ");

        // Create payment_method enum if not exists
        DB::statement("
            DO $$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'payment_method') THEN
                    CREATE TYPE payment_method AS ENUM ('CASH', 'CARD', 'MOBILE', 'TRANSFER', 'MIXED');
                END IF;
            END
            $$;
        ");
    }

    public function down()
    {
        DB::statement("DROP TYPE IF EXISTS receipt_status");
        DB::statement("DROP TYPE IF EXISTS payment_method");
    }
}
