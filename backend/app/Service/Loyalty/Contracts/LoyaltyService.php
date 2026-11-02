<?php

namespace App\Service\Loyalty\Contracts;

use App\Models\Bot\Customer;
use App\Models\Loyalty\PointsLedger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface LoyaltyService
{
    /**
     * Hitung perolehan poin dari transaksi pengisian daya berdasarkan kWh dan nominal.
     */
    public function calculatePoints(float $kwh, float $amount = 0.0): int;

    /**
     * Berikan penambahan kredit poin loyalitas ke akun pelanggan secara atomik.
     */
    public function awardPoints(
        Customer $customer,
        int $points,
        string $description,
        ?string $transactionId = null
    ): PointsLedger;

    /**
     * Kurangkan debet poin loyalitas dari akun pelanggan secara atomik.
     *
     * @throws \App\Exceptions\BadRequestException Jika saldo tidak mencukupi
     */
    public function deductPoints(
        Customer $customer,
        int $points,
        string $description
    ): PointsLedger;

    /**
     * Dapatkan riwayat buku besar (ledger) poin pelanggan dengan paginasi.
     */
    public function getCustomerLedger(string $customerId, int $limit = 20): LengthAwarePaginator;

    /**
     * Tentukan tier keanggotaan pelanggan berdasarkan total saldo poin saat ini.
     */
    public function resolveCustomerTier(int $pointsBalance): string;
}
