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
        Schema::create('customer_interactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('customer_id');
            $table->uuid('created_by')->nullable(); // user who logged the interaction
            $table->string('type'); // call, email, meeting, note, etc.
            $table->string('direction')->nullable(); // inbound, outbound (for calls/emails)
            $table->text('description');
            $table->json('metadata')->nullable(); // additional data like duration, attachments
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['customer_id', 'occurred_at']);
            $table->index(['customer_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_interactions');
    }
};