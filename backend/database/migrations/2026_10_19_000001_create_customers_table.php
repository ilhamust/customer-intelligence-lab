<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('customer_code')->unique();
            $table->string('name');
            $table->string('phone')->unique();
            $table->string('vehicle')->nullable();
            $table->string('driver_type')->nullable();
            $table->string('residential_area')->nullable();
            $table->string('primary_route')->nullable();
            $table->integer('points_balance')->default(0);
            $table->string('status')->default('active');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index('customer_code');
            $table->index('phone');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
