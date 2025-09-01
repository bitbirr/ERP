<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStockMovementRefToReceiptLinesTable extends Migration
{
    public function up()
    {
        Schema::table('receipt_lines', function (Blueprint $table) {
            $table->string('stock_movement_ref')->nullable()->after('line_total');
            $table->index('stock_movement_ref');
        });
    }

    public function down()
    {
        Schema::table('receipt_lines', function (Blueprint $table) {
            $table->dropIndex(['stock_movement_ref']);
            $table->dropColumn('stock_movement_ref');
        });
    }
}