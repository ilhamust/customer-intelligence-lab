<?php

namespace App\Ai\Tools;

use App\Models\Bot\Conversation;
use App\Service\Bot\Contracts\TicketService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class CreateSupportTicketTool implements Tool
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
        return 'create_support_ticket';
    }

    /**
     * Deskripsi tujuan tool untuk AI LLM.
     */
    public function description(): Stringable|string
    {
        return 'Eskalasikan percakapan pelanggan ke Customer Service manusia dan buatkan tiket antrean bantuan resmi jika terjadi salah satu dari 3 kendala: 1. Selisih saldo poin (points_mismatch), 2. Transaksi tidak biasa atau gagal pengisian daya (abnormal_transaction), 3. Komplain pelayanan (service_complaint).';
    }

    /**
     * Eksekusi pembuatan tiket dan pengembalian pesan antrean resmi.
     */
    public function handle(Request $request): Stringable|string
    {
        if (! $this->conversation) {
            return 'Gagal membuat tiket: Percakapan tidak ditemukan.';
        }

        $category = (string) $request->string('category');
        $description = (string) $request->string('description', 'Pengaduan pelanggan');

        // Validasi category agar selalu sesuai enum kategori TicketService
        $allowedCategories = [
            TicketService::CATEGORY_POINTS_MISMATCH,
            TicketService::CATEGORY_ABNORMAL_TRANSACTION,
            TicketService::CATEGORY_SERVICE_COMPLAINT,
        ];

        if (! in_array($category, $allowedCategories, true)) {
            $category = TicketService::CATEGORY_POINTS_MISMATCH;
        }

        /** @var TicketService $ticketService */
        $ticketService = app(TicketService::class);

        $ticket = $ticketService->createTicket(
            conversation: $this->conversation,
            category: $category,
            metadata: [
                'issue_summary' => $description,
                'escalated_by' => 'ai_agent',
            ]
        );

        Log::info('[CreateSupportTicketTool] Escalation ticket created or retrieved', [
            'ticket_id' => $ticket->id,
            'ticket_code' => $ticket->ticket_code,
            'category' => $ticket->category,
            'queue_position' => $ticket->queue_position,
        ]);

        $customerName = $this->conversation->customer?->name ?: ($this->conversation->name ?: 'Pelanggan');
        $queuePosition = $ticketService->calculateQueuePosition($ticket);

        return $ticketService->buildQueueNotificationText($queuePosition, $ticket->category, $customerName);
    }

    /**
     * Definisi skema input tool untuk AI LLM.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'category' => $schema->string()
                ->enum([
                    TicketService::CATEGORY_POINTS_MISMATCH,
                    TicketService::CATEGORY_ABNORMAL_TRANSACTION,
                    TicketService::CATEGORY_SERVICE_COMPLAINT,
                ])
                ->description('Kategori kendala: points_mismatch (selisih saldo poin), abnormal_transaction (transaksi tidak biasa/gagal), service_complaint (komplain pelayanan)')
                ->required(),
            'description' => $schema->string()
                ->description('Ringkasan kendala atau keluhan yang dialami pelanggan')
                ->required(),
        ];
    }
}
