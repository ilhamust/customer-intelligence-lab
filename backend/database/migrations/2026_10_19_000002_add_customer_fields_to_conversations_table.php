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
        Schema::table('conversations', function (Blueprint $table) {
            $table->foreignUlid('customer_id')
                ->nullable()
                ->after('device_id')
                ->constrained('customers')
                ->nullOnDelete();

            $table->string('registration_step')
                ->default('unregistered')
                ->after('handler');

            $table->index('registration_step');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropColumn(['customer_id', 'registration_step']);
        });
    }
};
