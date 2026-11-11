<?php

namespace App\Ai\Tools;

use App\Models\Bot\Conversation;
use App\Models\Bot\Customer;
use App\Service\Loyalty\Contracts\LoyaltyService;
use Carbon\Carbon;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetCustomerPointsHistoryTool implements Tool
{
    /**
     * Inisialisasi tool dengan relasi percakapan aktif.
     */
    public function __construct(
        protected ?Conversation $conversation = null
    ) {}

    /**
     * Nama fungsi tool untuk AI function calling.
     */
    public function name(): string
    {
        return 'get_customer_points_history';
    }

    /**
     * Deskripsi tujuan tool untuk AI LLM.
     */
    public function description(): Stringable|string
    {
        return 'Membaca riwayat buku besar mutasi poin (points ledger) milik pelanggan yang sedang berbicara secara aman dan read-only, mencakup saldo saat ini, status tier, dan hingga 5 transaksi perolehan poin terakhir.';
    }

    /**
     * Eksekusi pembacaan data riwayat poin pelanggan.
     */
    public function handle(Request $request): Stringable|string
    {
        $customerId = $this->conversation?->customer_id;

        if (! $customerId) {
            return 'Pelanggan belum terhubung atau belum terdaftar di sistem.';
        }

        $customer = $this->conversation->customer ?: Customer::find($customerId);

        if (! $customer) {
            return 'Data profil pelanggan tidak ditemukan.';
        }

        /** @var LoyaltyService $loyaltyService */
        $loyaltyService = app(LoyaltyService::class);

        $tier = $loyaltyService->resolveCustomerTier((int) $customer->points_balance);
        $ledger = $loyaltyService->getCustomerLedger($customer->id, 5);

        Log::info('[GetCustomerPointsHistoryTool] Points ledger retrieved for customer', [
            'customer_id' => $customer->id,
            'customer_code' => $customer->customer_code,
            'points_balance' => $customer->points_balance,
            'ledger_count' => $ledger->count(),
        ]);

        $lines = [];
        $lines[] = "Informasi Akun:";
        $lines[] = "• Nama: {$customer->name}";
        $lines[] = "• ID: {$customer->customer_code}";
        $lines[] = "• Saldo Saat Ini: {$customer->points_balance} Jarvis Points";
        $lines[] = "• Status Tier: {$tier}";
        $lines[] = "";

        if ($ledger->isEmpty()) {
            $lines[] = "Riwayat Mutasi: Belum ada catatan transaksi pengisian daya atau mutasi poin sebelumnya.";
        } else {
            $lines[] = "Riwayat 5 Transaksi Poin Terakhir:";
            foreach ($ledger as $idx => $entry) {
                $num = $idx + 1;
                $dateFormatted = $entry->created_at ? Carbon::parse($entry->created_at)->format('d/m/Y H:i') : '-';
                $sign = $entry->points >= 0 ? "+{$entry->points}" : "{$entry->points}";
                $lines[] = "{$num}. [{$dateFormatted}] {$entry->description} ({$sign} Poin | Saldo Akhir: {$entry->balance_after} Poin)";
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Definisi skema input tool untuk AI LLM.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'reason' => $schema->string()
                ->description('Konteks atau alasan pengecekan riwayat poin (opsional)')
                ->nullable(),
        ];
    }
}
