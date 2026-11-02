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
        Schema::create('charging_transactions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('external_id')->unique();
            $table->foreignUlid('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('phone')->index();
            $table->string('plate_number')->nullable()->index();
            $table->string('station_code')->index();
            $table->string('station_name');
            $table->string('charger_code')->index();
            $table->string('charger_type')->nullable();
            $table->decimal('kwh', 8, 2);
            $table->decimal('amount', 12, 2);
            $table->integer('duration_minutes')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at');
            $table->string('status')->default('completed')->index();
            $table->integer('points_awarded')->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index('finished_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('charging_transactions');
    }
};
