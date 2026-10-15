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
        Schema::create('messages', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->string('whatsapp_message_id')->nullable();
            $table->string('sender_type')->default('customer');
            $table->boolean('is_from_me')->default(false);
            $table->text('text');
            $table->string('status')->default('delivered');
            $table->timestampTz('sent_at');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index('conversation_id');
            $table->index('whatsapp_message_id');
            $table->index('sent_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
