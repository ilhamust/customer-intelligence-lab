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

        // Google Gemini (Provider Utama)
        if (config('ai.providers.gemini.key')) {
            $chain['gemini'] = env('AI_GEMINI_MODEL', null);
        }

        // Groq (Cadangan 1)
        if (config('ai.providers.groq.key')) {
            $chain['groq'] = env('AI_GROQ_MODEL', null);
        }

        // Ollama
        if (config('ai.providers.ollama.key')) {
            $chain['ollama'] = env('AI_OLLAMA_MODEL', null);
        }

        // OpenAI (Cadangan 2)
        if (config('ai.providers.openai.key')) {
            $chain['openai'] = env('AI_OPENAI_MODEL', 'gpt-4o-mini');
        }

        return ! empty($chain) ? $chain : [config('ai.default', 'gemini')];
    }

    /**
     * System Prompt / Persona Agent Customer Support Resmi Jarvis.
     *
     * Diintegrasikan dengan Knowledge Base resmi Jarvis EV Charging,
     * konteks profil pelanggan aktif (Customer 360°), dan strict domain guardrails.
     */
    public function instructions(): Stringable|string
    {
        $customer = $this->conversation?->customer;
        $customerName = $customer?->name ?: ($this->conversation?->name ?: 'Pelanggan');
        $customerCode = $customer?->customer_code ?: '-';
        $customerPhone = $customer?->phone ?: ($this->conversation?->phone ?: '-');
        $meta = is_array($this->conversation?->meta) ? $this->conversation->meta : [];

        $vehicle = $customer?->vehicle ?: ($meta['vehicle'] ?? $meta['kendaraan'] ?? 'Kendaraan Listrik');
        $driverType = $customer?->driver_type ?: ($meta['driver_type'] ?? $meta['segment'] ?? 'Driver EV');
        $residentialArea = $customer?->residential_area ?: '-';
        $primaryRoute = $customer?->primary_route ?: '-';
        $points = $customer?->points_balance !== null
            ? $customer->points_balance
            : (isset($meta['points']) || isset($meta['jarvis_points'])
                ? ($meta['points'] ?? $meta['jarvis_points'])
                : 'Belum terdata');

        $knowledgeContext = JarvisKnowledgeBase::getPromptContext();

        return <<<PROMPT
Anda adalah asisten virtual Customer Care resmi untuk "Jarvis" (Operator Stasiun Pengisian Daya Kendaraan Listrik / EV Charging Station).

{$knowledgeContext}

=== PROFIL PELANGGAN AKTIF (CUSTOMER 360°) ===
- ID Pelanggan: {$customerCode}
- Nama: {$customerName}
- WhatsApp: {$customerPhone}
- Kendaraan: {$vehicle}
- Tipe Driver / Segmen: {$driverType}
- Area Domisili / Tinggal: {$residentialArea}
- Rute Utama: {$primaryRoute}
- Saldo Jarvis Points: {$points}

PANDUAN KOMUNIKASI & INTERAKSI:
1. Sapa pelanggan dengan hangat, ramah, dan sopan (contoh: "Halo Kak {$customerName}").
2. Kenali profil kendaraan, rute, dan kebutuhan pelanggan berdasarkan data di atas tanpa meminta pelanggan mengulang informasi yang sudah ada di profil.
3. Selalu patuhi "STRICT DOMAIN GUARDRAILS":
   - HANYA layani pertanyaan seputar ekosistem pengisian kendaraan listrik Jarvis (lokasi stasiun, operasional 24 jam Sunset Road, fasilitas, tarif per kWh, dan Jarvis Points).
   - Jika pelanggan menanyakan topik di luar konteks (seperti koding/pemrograman, politik, resep masakan, dll.), tolak dengan santun dan arahkan kembali ke layanan Jarvis.
   - Jangan pernah mengarang data nominal atau transaksi riil jika data belum tercatat di sistem (Zero Hallucination).
4. ATURAN FORMAT TEKS WHATSAPP (BUKAN MARKDOWN):
   - WAJIB gunakan format WhatsApp:
     * Tebal / Bold: gunakan SATU tanda bintang *teks* (DILARANG KERAS menggunakan dua bintang **teks**).
     * Miring / Italic: gunakan satu garis bawah _teks_.
     * Coret / Strikethrough: gunakan satu tilde ~teks~.
   - DILARANG KERAS menggunakan format markdown seperti header (###, ##), tabel markdown (|---|), atau dua bintang (**).
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

    /**
     * Format dan bersihkan teks balasan AI agar sesuai dengan standar WhatsApp.
     * Mengonversi format Markdown umum (misal **teks** menjadi *teks*).
     */
    public static function formatForWhatsApp(string $text): string
    {
        // Ubah markdown header (### Judul atau ## Judul) menjadi bold WhatsApp (*Judul*)
        $text = preg_replace('/^#{1,6}\s+(.+)$/m', '*$1*', $text);

        // Ubah triple asterisks ***teks*** (bold + italic) menjadi *_teks_*
        $text = preg_replace('/\*{3}(.+?)\*{3}/s', '*_$1_*', $text);

        // Ubah double asterisks **teks** (bold) menjadi *teks*
        $text = preg_replace('/\*{2}(.+?)\*{2}/s', '*$1*', $text);

        // Ubah strikethrough ~~teks~~ menjadi ~teks~
        $text = preg_replace('/~~(.+?)~~/s', '~$1~', $text);

        return trim($text);
    }
}
