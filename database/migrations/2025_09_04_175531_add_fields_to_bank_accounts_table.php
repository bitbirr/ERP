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
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->string('account_type');
            $table->decimal('balance', 15, 2)->default(0);
            $table->uuid('branch_id');
            $table->uuid('customer_id');

            $table->foreign('branch_id')->references('id')->on('branches');
            $table->foreign('customer_id')->references('id')->on('customers');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropForeign(['customer_id']);
            $table->dropColumn(['account_type', 'balance', 'branch_id', 'customer_id']);
        });
    }
};
