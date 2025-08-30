<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

class CreateReceiptEnums extends Migration
{
    public function up()
    {
        DB::statement("CREATE TYPE receipt_status AS ENUM ('DRAFT', 'POSTED', 'VOIDED', 'REFUNDED')");
        DB::statement("CREATE TYPE payment_method AS ENUM ('CASH', 'CARD', 'MOBILE', 'TRANSFER', 'MIXED')");
    }

    public function down()
    {
        DB::statement("DROP TYPE IF EXISTS receipt_status");
        DB::statement("DROP TYPE IF EXISTS payment_method");
    }
}