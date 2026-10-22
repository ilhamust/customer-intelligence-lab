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
        Schema::create('tickets', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('ticket_code')->unique();
            $table->foreignUlid('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->foreignUlid('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('category')->default('general_human');
            $table->string('priority')->default('need_human');
            $table->string('status')->default('queued');
            $table->integer('queue_position')->default(1);
            $table->text('resolution_notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestampTz('resolved_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('conversation_id');
            $table->index('created_at');
            $table->index('ticket_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
