<?php

namespace App\Ai\Agents;

use App\Models\Bot\Conversation;
use App\Models\Bot\Message as DbMessage;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Promptable;
use Laravel\Ai\Providers\Tools\ProviderTool;
use Stringable;

class CustomerSupportAgent implements Agent, Conversational, HasTools
{
    use Promptable;

    /**
     * Inisialisasi agent dengan opsional konteks percakapan.
     *
     * @param  Conversation|null  $conversation  Model percakapan aktif
     * @param  string|null  $excludeMessageId  ID pesan yang sedang diproses agar tidak duplikat di riwayat
     */
    public function __construct(
        public ?Conversation $conversation = null,
        public ?string $excludeMessageId = null
    ) {}

    /**
     * Konfigurasi urutan Provider AI untuk Automatic Failover.
     *
     * Alur Failover:
     * 1. Utama: Google Gemini (gemini-2.5-flash / gemini-2.0-flash)
     * 2. Cadangan 1: Groq Cloud (llama-3.3-70b-versatile - ultra low latency)
     * 3. Cadangan 2: OpenAI (gpt-4o-mini)
     *
     * Jika provider utama mengalami HTTP 429 (Rate Limit) atau error server (5xx),
     * Laravel AI SDK secara otomatis mengalihkan request ke provider berikutnya.
     */
    public function provider(): array
    {
        $chain = [];
        $default = env('AI_DEFAULT_PROVIDER', 'groq');

        // Jika default adalah groq, posisikan groq di urutan pertama (ultra low latency < 1s)
        if ($default === 'groq' && config('ai.providers.groq.key')) {
            $chain['groq'] = env('AI_GROQ_MODEL', null);
        }

        // Google Gemini
        if (config('ai.providers.gemini.key')) {
            $chain['gemini'] = env('AI_GEMINI_MODEL', null);
        }

        // Groq sebagai cadangan jika bukan default
        if (! isset($chain['groq']) && config('ai.providers.groq.key')) {
            $chain['groq'] = env('AI_GROQ_MODEL', null);
        }

        // OpenAI sebagai cadangan
        if (config('ai.providers.openai.key')) {
            $chain['openai'] = env('AI_OPENAI_MODEL', 'gpt-4o-mini');
        }

        return ! empty($chain) ? $chain : [config('ai.default', 'groq')];
    }

    /**
     * System Prompt / Persona Agent Customer Support.
     *
     * Disesuaikan dengan karakteristik obrolan WhatsApp:
     * - Bahasa Indonesia yang sopan, ramah, dan solutif.
     * - Ringkas dan tidak bertele-tele (karena dibaca di layar HP WhatsApp).
     * - Menjaga keramahan dan empati kepada pelanggan.
     */
    public function instructions(): Stringable|string
    {
        $customerName = $this->conversation?->name ?: 'Pelanggan';

        return <<<PROMPT
Anda adalah asisten virtual Customer Care resmi untuk "Customer Intelligence Lab".
Nama pelanggan yang sedang Anda layani adalah: {$customerName}.

Panduan dalam membalas pesan:
1. Bersikaplah ramah, profesional, solutif, dan hangat dalam bahasa Indonesia yang baik.
2. Karena percakapan berlangsung di WhatsApp, buat balasan yang ringkas, jelas, to-the-point, dan mudah dibaca di ponsel. Hindari paragraf yang terlalu panjang.
3. Jawab pertanyaan pelanggan berdasarkan konteks riwayat percakapan sebelumnya.
4. Jika pelanggan menyampaikan kendala atau keluhan, tunjukkan empati terlebih dahulu sebelum memberikan solusi.
5. Jika ada hal teknis atau transaksi spesifik yang belum Anda ketahui, sampaikan dengan santun bahwa Anda mencatat pesan tersebut dan akan menghubungkan pelanggan dengan tim support kami.
6. Hindari menyebutkan istilah teknis internal seperti prompt, model LLM, token, atau sistem database.
PROMPT;
    }

    /**
     * Memuat riwayat percakapan sebelumnya ke dalam memori Agent.
     *
     * Mengambil 8 pesan terakhir dari database untuk memberikan konteks obrolan,
     * lalu memetakannya ke UserMessage (pesan customer) dan AssistantMessage (pesan bot/operator).
     *
     * @return Message[]
     */
    public function messages(): iterable
    {
        if (! $this->conversation) {
            return [];
        }

        $query = $this->conversation->messages()
            ->orderBy('sent_at', 'desc');

        if ($this->excludeMessageId) {
            $query->where('id', '!=', $this->excludeMessageId);
        }

        /** @var DbMessage[] $recentMessages */
        $recentMessages = $query->limit(8)->get()->reverse();

        $history = [];
        foreach ($recentMessages as $msg) {
            $text = trim((string) $msg->text);
            if ($text === '') {
                continue;
            }

            if ($msg->is_from_me || $msg->sender_type === 'bot' || $msg->sender_type === 'human') {
                $history[] = new AssistantMessage($text);
            } else {
                $history[] = new UserMessage($text);
            }
        }

        return $history;
    }

    /**
     * Get the tools available to the agent.
     *
     * @return list<Agent|Tool|ProviderTool>
     */
    public function tools(): iterable
    {
        return [];
    }
}
