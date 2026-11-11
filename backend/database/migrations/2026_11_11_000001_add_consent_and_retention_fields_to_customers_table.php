<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->boolean('is_marketing_consent')->default(true)->after('status');
            $table->timestamp('consented_at')->nullable()->after('is_marketing_consent');
            $table->timestamp('opted_out_at')->nullable()->after('consented_at');
            $table->timestamp('last_charged_at')->nullable()->after('opted_out_at');
            $table->timestamp('inactive_detected_at')->nullable()->after('last_charged_at');
            $table->timestamp('last_retention_sent_at')->nullable()->after('inactive_detected_at');

            $table->index('last_charged_at');
        });

        // Backfill ringan data existing:
        // 1. Isi last_charged_at dari transaksi charging terakhir (jika ada)
        DB::statement('
            UPDATE customers c
            SET last_charged_at = sub.max_finished_at
            FROM (
                SELECT customer_id, MAX(finished_at) AS max_finished_at
                FROM charging_transactions
                WHERE customer_id IS NOT NULL
                GROUP BY customer_id
            ) sub
            WHERE c.id = sub.customer_id
        ');

        // 2. Set default consented_at sama dengan waktu registrasi untuk pelanggan existing
        DB::statement('
            UPDATE customers
            SET consented_at = created_at
            WHERE consented_at IS NULL
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['last_charged_at']);
            $table->dropColumn([
                'is_marketing_consent',
                'consented_at',
                'opted_out_at',
                'last_charged_at',
                'inactive_detected_at',
                'last_retention_sent_at',
            ]);
        });
    }
};
