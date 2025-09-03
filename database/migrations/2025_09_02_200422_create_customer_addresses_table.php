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
        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('customer_id');
            $table->string('type')->default('billing'); // billing, shipping, etc.
            $table->string('region')->nullable(); // Ethiopia regions
            $table->string('zone')->nullable(); // Zones within regions
            $table->string('woreda')->nullable(); // Woredas within zones
            $table->string('kebele')->nullable(); // Kebeles within woredas
            $table->string('city')->nullable();
            $table->text('street_address')->nullable();
            $table->string('postal_code')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->index(['customer_id', 'type']);
            $table->index(['customer_id', 'is_primary']);
            $table->index(['region', 'zone', 'woreda', 'kebele']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_addresses');
    }
};