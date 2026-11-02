<?php

namespace App\Service\Loyalty\Implements;

use App\Exceptions\BadRequestException;
use App\Exceptions\NotFoundException;
use App\Models\Bot\Customer;
use App\Models\Loyalty\PointsLedger;
use App\Service\Loyalty\Contracts\LoyaltyService;
use App\Traits\ServiceHelper;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class LoyaltyServiceImpl implements LoyaltyService
{
    use ServiceHelper;

    /**
     * Hitung perolehan poin dari transaksi pengisian daya berdasarkan kWh.
     * Aturan default: 1 kWh = 10 Poin (dibulatkan ke bilangan bulat).
     */
    public function calculatePoints(float $kwh, float $amount = 0.0): int
    {
        if ($kwh <= 0) {
            return 0;
        }

        $ratio = (float) config('loyalty.kwh_to_points_ratio', 10);
        $points = (int) round($kwh * $ratio);

        return max(0, $points);
    }

    /**
     * Berikan penambahan kredit poin loyalitas ke akun pelanggan secara atomik.
     * Menggunakan DB::transaction dan pessimistic locking (lockForUpdate) untuk mencegah race condition.
     */
    public function awardPoints(
        Customer $customer,
        int $points,
        string $description,
        ?string $transactionId = null
    ): PointsLedger {
        $m = __METHOD__;

        if ($points <= 0) {
            throw new BadRequestException('Jumlah poin yang diberikan harus lebih besar dari 0.');
        }

        return DB::transaction(function () use ($customer, $points, $description, $transactionId, $m) {
            // Lock record customer untuk pembaruan saldo yang aman secara konkurensi
            /** @var Customer $lockedCustomer */
            $lockedCustomer = Customer::where('id', $customer->id)->lockForUpdate()->firstOrFail();

            $newBalance = $lockedCustomer->points_balance + $points;

            // 1. Simpan baris mutasi ke points_ledger
            $ledger = PointsLedger::create([
                'customer_id' => $lockedCustomer->id,
                'transaction_id' => $transactionId,
                'type' => 'credit',
                'points' => $points,
                'balance_after' => $newBalance,
                'description' => $description,
                'created_at' => now(),
            ]);

            // 2. Perbarui points_balance pada customer
            $lockedCustomer->update([
                'points_balance' => $newBalance,
            ]);

            $this->logInfo($m, 'Loyalty points credited successfully', [
                'customer_id' => $lockedCustomer->id,
                'customer_code' => $lockedCustomer->customer_code,
                'points_credited' => $points,
                'balance_after' => $newBalance,
                'transaction_id' => $transactionId,
            ]);

            return $ledger;
        });
    }

    /**
     * Kurangkan debet poin loyalitas dari akun pelanggan secara atomik.
     *
     * @throws BadRequestException Jika saldo poin tidak mencukupi
     */
    public function deductPoints(
        Customer $customer,
        int $points,
        string $description
    ): PointsLedger {
        $m = __METHOD__;

        if ($points <= 0) {
            throw new BadRequestException('Jumlah poin yang dikurangkan harus lebih besar dari 0.');
        }

        return DB::transaction(function () use ($customer, $points, $description, $m) {
            /** @var Customer $lockedCustomer */
            $lockedCustomer = Customer::where('id', $customer->id)->lockForUpdate()->firstOrFail();

            if ($lockedCustomer->points_balance < $points) {
                throw new BadRequestException("Saldo poin tidak mencukupi. Saldo saat ini: {$lockedCustomer->points_balance}, poin yang dibutuhkan: {$points}.");
            }

            $newBalance = $lockedCustomer->points_balance - $points;

            // 1. Simpan baris mutasi debit ke points_ledger
            $ledger = PointsLedger::create([
                'customer_id' => $lockedCustomer->id,
                'transaction_id' => null,
                'type' => 'debit',
                'points' => $points,
                'balance_after' => $newBalance,
                'description' => $description,
                'created_at' => now(),
            ]);

            // 2. Perbarui points_balance pada customer
            $lockedCustomer->update([
                'points_balance' => $newBalance,
            ]);

            $this->logInfo($m, 'Loyalty points debited successfully', [
                'customer_id' => $lockedCustomer->id,
                'customer_code' => $lockedCustomer->customer_code,
                'points_debited' => $points,
                'balance_after' => $newBalance,
            ]);

            return $ledger;
        });
    }

    /**
     * Dapatkan riwayat buku besar (ledger) poin pelanggan dengan paginasi.
     */
    public function getCustomerLedger(string $customerId, int $limit = 20): LengthAwarePaginator
    {
        $customer = Customer::find($customerId);
        if (! $customer) {
            throw new NotFoundException("Customer [{$customerId}] not found.");
        }

        return PointsLedger::with(['transaction'])
            ->where('customer_id', $customerId)
            ->orderBy('created_at', 'desc')
            ->paginate($limit);
    }

    /**
     * Tentukan tier keanggotaan pelanggan berdasarkan total saldo poin saat ini.
     */
    public function resolveCustomerTier(int $pointsBalance): string
    {
        $tiers = config('loyalty.tiers', [
            'platinum' => 1000,
            'gold' => 500,
            'silver' => 0,
        ]);

        if ($pointsBalance >= ($tiers['platinum'] ?? 1000)) {
            return 'Platinum';
        }

        if ($pointsBalance >= ($tiers['gold'] ?? 500)) {
            return 'Gold';
        }

        return 'Silver';
    }
}
