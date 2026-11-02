<?php

namespace App\Service\Bot\Implements;

use App\Models\Bot\Conversation;
use App\Models\Bot\Customer;
use App\Models\Charging\ChargingTransaction;
use App\Service\Bot\Contracts\CustomerRegistrationService;
use App\Service\Loyalty\Contracts\LoyaltyService;
use App\Traits\ServiceHelper;

class CustomerRegistrationServiceImpl implements CustomerRegistrationService
{
    use ServiceHelper;

    public function __construct(
        protected LoyaltyService $loyaltyService
    ) {}

    /**
     * Get the formatted WhatsApp text template for the registration form.
     */
    public function getRegistrationFormText(): string
    {
        return <<<'TXT'
*Selamat Datang di Jarvis EV Charging!* ⚡
Stasiun pengisian daya kendaraan listrik terpercaya.

Untuk kenyamanan Anda dan mendapatkan akses *Wi-Fi Gratis 30 Menit* serta *Jarvis Points*, mohon lengkapi data pendaftaran singkat berikut:

Nama: 
Kendaraan: (contoh: BYD M6 / Ioniq 5 / Wuling Air EV)
Plat Nomor: (contoh: DK 1234 ABC)
Tipe Driver: (Ride-hailing / Private / Fleet)
Area Tinggal: (contoh: Jimbaran)
Rute Utama: (contoh: Jimbaran - Bandara - Seminyak)

_Silakan salin pesan ini atau ketik jawaban Anda di bawah._
TXT;
    }

    /**
     * Parse and extract customer registration fields from a chat reply.
     *
     * @return array{
     *     name: string,
     *     vehicle: string,
     *     plate_number: string|null,
     *     driver_type: string,
     *     residential_area: string,
     *     primary_route: string
     * }|null Returns null if required fields cannot be extracted
     */
    public function parseRegistrationText(string $text): ?array
    {
        $cleanText = trim($text);
        if ($cleanText === '') {
            return null;
        }

        // Regex ekstraksi field dengan toleransi variasi penulisan driver WhatsApp
        $name = $this->extractField($cleanText, '/(?:nama|name)\s*[:=\-]\s*([^\n\r]+)/iu');
        $vehicle = $this->extractField($cleanText, '/(?:kendaraan|mobil|vehicle|ev|model)\s*[:=\-]\s*([^\n\r]+)/iu');
        $plateRaw = $this->extractField($cleanText, '/(?:plat(?:\s*nomor)?|no(?:\s*polisi)?|nopol|plate(?:\s*number)?)\s*[:=\-]\s*([^\n\r]+)/iu');
        $driverTypeRaw = $this->extractField($cleanText, '/(?:tipe\s*(?:driver)?|driver\s*type|tipe)\s*[:=\-]\s*([^\n\r]+)/iu');
        $area = $this->extractField($cleanText, '/(?:area(?:\s*tinggal)?|tempat\s*tinggal|domisili|lokasi)\s*[:=\-]\s*([^\n\r]+)/iu');
        $route = $this->extractField($cleanText, '/(?:rute(?:\s*utama)?|route)\s*[:=\-]\s*([^\n\r]+)/iu');

        // Validasi field minimum: Nama dan Kendaraan wajib terisi
        if (! $name || ! $vehicle) {
            return null;
        }

        // Bersihkan teks dari sisa kurung instruksi (contoh: "BYD M6 (contoh: ...)" -> "BYD M6")
        $vehicleClean = preg_replace('/\s*\(contoh.*?\)/iu', '', $vehicle);
        $vehicleClean = trim((string) $vehicleClean);

        // Normalisasi format plat nomor (uppercase dan trim)
        $plateNumber = null;
        if ($plateRaw) {
            $plateClean = preg_replace('/\s*\(contoh.*?\)/iu', '', $plateRaw);
            $plateClean = strtoupper(trim(preg_replace('/\s+/', ' ', (string) $plateClean)));
            if ($plateClean !== '' && $plateClean !== '-') {
                $plateNumber = $plateClean;
            }
        }

        // Normalisasi driver_type ke kategori standar ('ride-hailing', 'private', 'fleet')
        $driverType = $this->normalizeDriverType($driverTypeRaw);

        return [
            'name' => trim($name),
            'vehicle' => $vehicleClean !== '' ? $vehicleClean : trim($vehicle),
            'plate_number' => $plateNumber,
            'driver_type' => $driverType,
            'residential_area' => $area ? trim($area) : '-',
            'primary_route' => $route ? trim($route) : '-',
        ];
    }

    /**
     * Register a new customer, persist to database, and link to conversation.
     *
     * @param  array{
     *     name: string,
     *     vehicle: string,
     *     plate_number?: string|null,
     *     driver_type: string,
     *     residential_area?: string|null,
     *     primary_route?: string|null
     * }  $data
     */
    public function registerCustomer(Conversation $conversation, array $data): Customer
    {
        $m = __METHOD__;
        $phone = $conversation->phone ?: preg_replace('/[^0-9]/', '', explode('@', $conversation->chat_jid)[0]);

        // Cek apakah customer dengan nomor HP ini sudah ada
        $customer = Customer::where('phone', $phone)->first();

        if ($customer) {
            $customer->update([
                'name' => $data['name'],
                'vehicle' => $data['vehicle'],
                'plate_number' => $data['plate_number'] ?? $customer->plate_number,
                'driver_type' => $data['driver_type'],
                'residential_area' => $data['residential_area'] ?? $customer->residential_area,
                'primary_route' => $data['primary_route'] ?? $customer->primary_route,
            ]);

            $this->logInfo($m, 'Existing customer profile updated via chat registration', [
                'customer_id' => $customer->id,
                'customer_code' => $customer->customer_code,
                'phone' => $phone,
            ]);
        } else {
            $customerCode = Customer::generateCustomerCode();

            $customer = Customer::create([
                'customer_code' => $customerCode,
                'name' => $data['name'],
                'phone' => $phone,
                'vehicle' => $data['vehicle'],
                'plate_number' => $data['plate_number'] ?? null,
                'driver_type' => $data['driver_type'],
                'residential_area' => $data['residential_area'] ?? '-',
                'primary_route' => $data['primary_route'] ?? '-',
                'points_balance' => 0,
                'status' => 'active',
            ]);

            $this->logInfo($m, 'New customer registered successfully', [
                'customer_id' => $customer->id,
                'customer_code' => $customerCode,
                'phone' => $phone,
            ]);
        }

        // 3. Mekanisme Retroactive Claiming: Hubungkan transaksi charging terdahulu (Non-Member)
        $unlinkedTransactions = ChargingTransaction::whereNull('customer_id')
            ->where(function ($q) use ($phone, $customer) {
                $q->where('phone', $phone);
                if (! empty($customer->plate_number)) {
                    $q->orWhere('plate_number', $customer->plate_number)
                      ->orWhereRaw('UPPER(plate_number) = ?', [strtoupper($customer->plate_number)]);
                }
            })
            ->get();

        $claimedPointsTotal = 0;
        $claimedCount = 0;

        foreach ($unlinkedTransactions as $trx) {
            $points = $this->loyaltyService->calculatePoints((float) $trx->kwh, (float) $trx->amount);

            $trx->update([
                'customer_id' => $customer->id,
                'points_awarded' => $points,
            ]);

            if ($points > 0) {
                $description = sprintf('Klaim retroaktif pengisian daya %.2f kWh di %s (#%s)', $trx->kwh, $trx->station_name, $trx->external_id);
                $this->loyaltyService->awardPoints($customer, $points, $description, $trx->id);
                $claimedPointsTotal += $points;
            }

            $claimedCount++;
        }

        if ($claimedCount > 0) {
            $this->logInfo($m, 'Retroactive charging transactions claimed successfully for newly registered customer', [
                'customer_id' => $customer->id,
                'claimed_count' => $claimedCount,
                'claimed_points_total' => $claimedPointsTotal,
            ]);
            $customer->refresh();
        }

        // Tautkan customer ke percakapan dan tandai alur registrasi selesai
        $conversation->update([
            'customer_id' => $customer->id,
            'registration_step' => 'completed',
            'name' => $customer->name,
            'meta' => array_merge($conversation->meta ?? [], [
                'vehicle' => $customer->vehicle,
                'plate_number' => $customer->plate_number,
                'driver_type' => $customer->driver_type,
                'residential_area' => $customer->residential_area,
                'primary_route' => $customer->primary_route,
                'customer_code' => $customer->customer_code,
                'claimed_retroactive_points' => $claimedPointsTotal,
                'claimed_retroactive_count' => $claimedCount,
            ]),
        ]);

        return $customer;
    }

    /**
     * Build the official WhatsApp welcome message text containing customer ID, Wi-Fi token, and claimed points.
     *
     * @param  array{
     *     ssid: string,
     *     username: string,
     *     password: string,
     *     duration_minutes: int,
     *     expires_at_formatted: string
     * }  $wifiToken
     */
    public function buildWelcomeMessageText(
        Customer $customer,
        array $wifiToken,
        int $claimedPoints = 0,
        int $claimedCount = 0
    ): string {
        $name = $customer->name;
        $code = $customer->customer_code;
        $ssid = $wifiToken['ssid'];
        $username = $wifiToken['username'];
        $password = $wifiToken['password'];
        $expiry = $wifiToken['expires_at_formatted'];

        $retroactiveBlock = '';
        if ($claimedPoints > 0) {
            $retroactiveBlock = "\n🎁 *Klaim Poin Pengisian Sebelumnya:*\n" .
                "Selamat! {$claimedCount} transaksi pengisian daya Anda terdahulu berhasil diklaim.\n" .
                "👉 *+{$claimedPoints} Jarvis Points* telah ditambahkan ke akun Anda! (Saldo: {$customer->points_balance} Poin)\n";
        }

        return <<<TXT
*Selamat Bergabung di Jarvis, Kak {$name}!* 🎉

Data pendaftaran Anda telah tersimpan dengan aman.
ID Pelanggan resmi Anda:
👉 *{$code}*
{$retroactiveBlock}
Berikut adalah akses *Wi-Fi Gratis 30 Menit* Anda di stasiun Jarvis:
• SSID: *{$ssid}*
• Username: *{$username}*
• Password: *{$password}*
• Berlaku s.d: *{$expiry}*

Kini Anda dapat langsung melakukan pengisian daya kendaraan listrik Anda dan mengumpulkan *Jarvis Points* di setiap sesi pengisian! ⚡

Ada yang bisa kami bantu seputar stasiun charging hari ini?
TXT;
    }

    /**
     * Get the gentle reminder guide text when registration input is incomplete.
     */
    public function getIncompleteGuideText(): string
    {
        return <<<'TXT'
Mohon maaf Kak, data pendaftaran belum terbaca lengkap 🙏

Untuk mengaktifkan akses *Wi-Fi Gratis 30 Menit* dan *Jarvis Points*, mohon lengkapi data dengan format berikut:

Nama: (nama Anda)
Kendaraan: (contoh: BYD M6)
Plat Nomor: (contoh: DK 1234 ABC)
Tipe Driver: (Ride-hailing / Private / Fleet)
Area Tinggal: (contoh: Jimbaran)
Rute Utama: (contoh: Jimbaran - Bandara - Seminyak)
TXT;
    }

    /**
     * Helper to extract a single field by regular expression.
     */
    protected function extractField(string $text, string $pattern): ?string
    {
        if (preg_match($pattern, $text, $matches)) {
            $val = trim($matches[1]);

            return $val !== '' ? $val : null;
        }

        return null;
    }

    /**
     * Helper to normalize driver type string.
     */
    protected function normalizeDriverType(?string $rawType): string
    {
        if (! $rawType) {
            return 'private';
        }

        $lower = strtolower($rawType);

        if (str_contains($lower, 'ride') || str_contains($lower, 'taksi') || str_contains($lower, 'ojol') || str_contains($lower, 'grab') || str_contains($lower, 'gojek') || str_contains($lower, 'maxim') || str_contains($lower, 'online')) {
            return 'ride-hailing';
        }

        if (str_contains($lower, 'fleet') || str_contains($lower, 'armada') || str_contains($lower, 'perusahaan') || str_contains($lower, 'pt') || str_contains($lower, 'kantor')) {
            return 'fleet';
        }

        return 'private';
    }
}
