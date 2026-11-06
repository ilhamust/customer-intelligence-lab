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
        Schema::create('urgent_alerts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('alert_code')->unique();
            $table->foreignUlid('conversation_id')->nullable()->constrained('conversations')->nullOnDelete();
            $table->foreignUlid('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignUlid('ticket_id')->nullable()->constrained('tickets')->nullOnDelete();
            $table->string('incident_category')->default('general_emergency')->index();
            $table->text('raw_message');
            $table->json('dispatched_recipients')->nullable();
            $table->string('status')->default('triggered')->index();
            $table->string('acknowledged_by')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('urgent_alerts');
    }
};

