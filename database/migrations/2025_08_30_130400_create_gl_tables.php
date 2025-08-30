<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGlTables extends Migration
{
    public function up()
    {
        Schema::create('gl_journals', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('source');
            $table->uuid('source_id');
            $table->uuid('branch_id');
            $table->date('date');
            $table->string('memo');
            $table->timestamp('posted_at')->nullable();
            $table->uuid('posted_by')->nullable();
            $table->timestamps();
        });

        Schema::create('gl_lines', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('journal_id');
            $table->string('account_code');
            $table->decimal('debit', 16, 2)->check('debit >= 0');
            $table->decimal('credit', 16, 2)->check('credit >= 0');
            $table->jsonb('meta')->nullable();
            $table->timestamps();

            $table->foreign('journal_id')->references('id')->on('gl_journals');
        });
    }

    public function down()
    {
        Schema::dropIfExists('gl_lines');
        Schema::dropIfExists('gl_journals');
    }
}