<?php

namespace App\Ai\Agents;

use App\Ai\Knowledge\JarvisKnowledgeBase;
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
     * System Prompt / Persona Agent Customer Support Resmi Jarvis.
     *
     * Diintegrasikan dengan Knowledge Base resmi Jarvis EV Charging,
     * konteks profil pelanggan aktif (Customer 360°), dan strict domain guardrails.
     */
    public function instructions(): Stringable|string
    {
        $customerName = $this->conversation?->name ?: 'Pelanggan';
        $customerPhone = $this->conversation?->phone ?: '-';
        $meta = is_array($this->conversation?->meta) ? $this->conversation->meta : [];

        $vehicle = $meta['vehicle'] ?? $meta['kendaraan'] ?? 'Kendaraan Listrik';
        $driverType = $meta['driver_type'] ?? $meta['segment'] ?? 'Driver EV';
        $points = isset($meta['points']) || isset($meta['jarvis_points'])
            ? ($meta['points'] ?? $meta['jarvis_points'])
            : 'Belum terdata';

        $knowledgeContext = JarvisKnowledgeBase::getPromptContext();

        return <<<PROMPT
Anda adalah asisten virtual Customer Care resmi untuk "Jarvis" (Operator Stasiun Pengisian Daya Kendaraan Listrik / EV Charging Station).

{$knowledgeContext}

=== PROFIL PELANGGAN AKTIF (CUSTOMER 360°) ===
- Nama: {$customerName}
- WhatsApp: {$customerPhone}
- Kendaraan: {$vehicle}
- Tipe Driver / Segmen: {$driverType}
- Saldo Jarvis Points: {$points}

PANDUAN KOMUNIKASI & INTERAKSI:
1. Sapa pelanggan dengan hangat, ramah, dan sopan (contoh: "Halo Kak {$customerName}").
2. Kenali profil kendaraan dan kebutuhan pelanggan berdasarkan data di atas tanpa meminta pelanggan mengulang informasi yang sudah ada di profil.
3. Selalu patuhi "STRICT DOMAIN GUARDRAILS":
   - HANYA layani pertanyaan seputar ekosistem pengisian kendaraan listrik Jarvis (lokasi stasiun, operasional 24 jam Sunset Road, fasilitas, tarif per kWh, dan Jarvis Points).
   - Jika pelanggan menanyakan topik di luar konteks (seperti koding/pemrograman, politik, resep masakan, dll.), tolak dengan santun dan arahkan kembali ke layanan Jarvis.
   - Jangan pernah mengarang data nominal atau transaksi riil jika data belum tercatat di sistem (Zero Hallucination).
4. ATURAN FORMAT TEKS WHATSAPP (BUKAN MARKDOWN):
   - Bold / Huruf Tebal: WAJIB gunakan SATU tanda bintang *teks* (DILARANG menggunakan dua bintang **teks**).
   - Italic / Huruf Miring: gunakan _teks_.
   - DILARANG menggunakan format markdown umum seperti header (###, ##), tabel markdown (|---|), atau **teks**.
   - Gunakan tanda minus (- ) atau bullet (• ) untuk daftar/poin.
5. Buat jawaban ringkas, solutif, to-the-point, dan ramah untuk dibaca di WhatsApp.
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

        $query = DbMessage::where('conversation_id', $this->conversation->id)
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
