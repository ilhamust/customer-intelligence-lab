
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
        Schema::create('conversations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('device_id');
            $table->string('chat_jid');
            $table->string('name')->nullable();
            $table->string('phone')->nullable();
            $table->text('last_message')->nullable();
            $table->timestampTz('last_message_time')->nullable();
            $table->integer('unread_count')->default(0);
            $table->string('status')->default('all');
            $table->string('handler')->default('ai');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['device_id', 'chat_jid']);
            $table->index('device_id');
            $table->index('chat_jid');
            $table->index('status');
            $table->index('last_message_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
