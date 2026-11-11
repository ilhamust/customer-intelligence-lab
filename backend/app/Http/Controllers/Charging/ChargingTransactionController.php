<?php

namespace App\Http\Controllers\Charging;

use App\Events\Charging\ChargingTransactionCompleted;
use App\Exceptions\BadRequestException;
use App\Exceptions\InternalServerException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Charging\StoreChargingTransactionRequest;
use App\Http\Resources\Api\DataResource;
use App\Http\Resources\Api\ErrorResource;
use App\Http\Resources\Api\InternalErrorResource;
use App\Jobs\Charging\SendChargingReceiptJob;
use App\Models\Bot\Customer;
use App\Models\Charging\ChargingTransaction;
use App\Service\Loyalty\Contracts\LoyaltyService;
use App\Traits\ControllerHelpers;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChargingTransactionController extends Controller
{
    use ControllerHelpers;

    public function __construct(
        protected LoyaltyService $loyaltyService
    ) {}

    /**
     * Ingest or record an EV charging transaction with idempotency and customer matching.
     */
    public function store(StoreChargingTransactionRequest $request): DataResource|ErrorResource|InternalErrorResource
    {
        $ctx = __METHOD__;

        try {
            $validated = $request->validated();
            $externalId = trim((string) $validated['external_id']);

            // 1. Proteksi Idempotensi: Cek apakah external_id sudah tercatat sebelumnya
            $existing = ChargingTransaction::with(['customer', 'pointsLedger'])
                ->where('external_id', $externalId)
                ->first();

            if ($existing) {
                return $this->respondWithData([
                    'idempotent' => true,
                    'matched_customer' => $existing->customer_id !== null,
                    'transaction' => $existing,
                ], 'Transaksi dengan ID eksternal ini telah diproses sebelumnya (Idempotent PASS).');
            }

            // 2. Normalisasi Identitas (Nomor HP & Plat Nomor)
            $rawPhone = $validated['phone'] ?? null;
            $normalizedPhone = $this->normalizePhone($rawPhone);

            $rawPlate = $validated['plate_number'] ?? null;
            $normalizedPlate = $this->normalizePlateNumber($rawPlate);

            // 3. Algoritma Pencocokan Pelanggan (Customer Matching Logic)
            // Prioritas 1: Pencocokan via Nomor Telepon baku (628...)
            $customer = null;
            if ($normalizedPhone) {
                $customer = Customer::where('phone', $normalizedPhone)->first();
            }

            // Prioritas 2: Pencocokan via Plat Nomor Kendaraan (jika tidak ditemukan via HP)
            if (! $customer && $normalizedPlate) {
                $customer = Customer::whereRaw('UPPER(plate_number) = ?', [$normalizedPlate])->first();
            }

            $kwh = (float) $validated['kwh'];
            $amount = (float) $validated['amount'];
            $stationCode = $validated['station_code'];
            $stationName = $validated['station_name'] ?? $stationCode;

            // 4. Atomik Transaksi Database: Catat Transaksi Charging & Mutasi Poin
            $transaction = DB::transaction(function () use (
                $validated,
                $externalId,
                $normalizedPhone,
                $normalizedPlate,
                $stationCode,
                $stationName,
                $kwh,
                $amount,
                $customer
            ) {
                // Hitung perolehan poin hanya jika pelanggan terdaftar (Member)
                $pointsAwarded = 0;
                if ($customer) {
                    $pointsAwarded = $this->loyaltyService->calculatePoints($kwh, $amount);
                }

                // Buat record charging_transactions
                $trx = ChargingTransaction::create([
                    'external_id' => $externalId,
                    'customer_id' => $customer?->id,
                    'phone' => (string) ($normalizedPhone ?? ($validated['phone'] ?? '')),
                    'plate_number' => $normalizedPlate,
                    'station_code' => $stationCode,
                    'station_name' => $stationName,
                    'charger_code' => $validated['charger_code'],
                    'charger_type' => $validated['charger_type'] ?? null,
                    'kwh' => $kwh,
                    'amount' => $amount,
                    'duration_minutes' => $validated['duration_minutes'] ?? null,
                    'started_at' => $validated['started_at'] ?? null,
                    'finished_at' => $validated['finished_at'],
                    'status' => $validated['status'] ?? 'completed',
                    'points_awarded' => $pointsAwarded,
                    'meta' => $validated['meta'] ?? null,
                ]);

                // Mutasi kredit poin ke buku besar loyalitas pelanggan
                if ($customer && $pointsAwarded > 0) {
                    $description = sprintf('Pengisian daya %.2f kWh di %s (%s)', $kwh, $stationName, $stationCode);
                    $this->loyaltyService->awardPoints($customer, $pointsAwarded, $description, $trx->id);
                }

                // Perbarui waktu transaksi terakhir & pulihkan status pelanggan jika sebelumnya inactive
                if ($customer) {
                    $customer->update([
                        'last_charged_at' => $trx->finished_at,
                        'status' => 'active',
                        'inactive_detected_at' => null,
                    ]);
                }

                return $trx->load(['customer', 'pointsLedger']);
            });

            // 5. Dispatch Event & Antrean Kuitansi Digital Asinkron ke WhatsApp
            ChargingTransactionCompleted::dispatch($transaction, $customer !== null);
            SendChargingReceiptJob::dispatch($transaction->id);

            return $this->respondWithData([
                'idempotent' => false,
                'matched_customer' => $customer !== null,
                'transaction' => $transaction,
            ], 'Transaksi charging berhasil dicatat.');
        } catch (BadRequestException $e) {
            return $this->handleBadRequest($e, $ctx);
        } catch (InternalServerException $e) {
            return $this->handleInternal($e, $ctx);
        } catch (Exception $e) {
            return $this->handleUnexpected($e, $ctx, 'Gagal memproses transaksi pengisian daya.');
        }
    }

    /**
     * List all charging transactions with filtering, KPI summary, and pagination for Back Office.
     */
    public function index(Request $request): DataResource|ErrorResource|InternalErrorResource
    {
        $ctx = __METHOD__;

        try {
            $search = trim((string) $request->input('search', ''));
            $stationCode = $request->input('station_code');
            $status = $request->input('status');
            $customerId = $request->input('customer_id');
            $dateFrom = $request->input('date_from');
            $dateTo = $request->input('date_to');
            $perPage = (int) $request->input('per_page', 15);

            $query = ChargingTransaction::with(['customer', 'pointsLedger']);

            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('external_id', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('plate_number', 'like', "%{$search}%")
                        ->orWhere('station_name', 'like', "%{$search}%")
                        ->orWhere('charger_code', 'like', "%{$search}%");
                });
            }

            if (! empty($stationCode) && $stationCode !== 'all') {
                $query->where('station_code', $stationCode);
            }

            if (! empty($status) && $status !== 'all') {
                $query->where('status', $status);
            }

            if (! empty($customerId)) {
                $query->where('customer_id', $customerId);
            }

            if (! empty($dateFrom)) {
                $query->where('finished_at', '>=', $dateFrom);
            }

            if (! empty($dateTo)) {
                $query->where('finished_at', '<=', $dateTo);
            }

            $paginated = (clone $query)->orderBy('finished_at', 'desc')->paginate($perPage);

            // KPI Summary Metrics
            $summary = [
                'total_transactions' => (clone $query)->count(),
                'total_kwh' => round((float) (clone $query)->sum('kwh'), 2),
                'total_revenue' => (float) (clone $query)->sum('amount'),
                'total_points_awarded' => (int) (clone $query)->sum('points_awarded'),
                'member_transactions' => (clone $query)->whereNotNull('customer_id')->count(),
                'non_member_transactions' => (clone $query)->whereNull('customer_id')->count(),
            ];

            $response = [
                'data' => $paginated->items(),
                'meta' => [
                    'current_page' => $paginated->currentPage(),
                    'last_page' => $paginated->lastPage(),
                    'per_page' => $paginated->perPage(),
                    'total' => $paginated->total(),
                    'from' => $paginated->firstItem(),
                    'to' => $paginated->lastItem(),
                ],
                'summary' => $summary,
            ];

            return $this->respondWithData($response, 'Successfully retrieved charging transactions list');
        } catch (BadRequestException $e) {
            return $this->handleBadRequest($e, $ctx);
        } catch (InternalServerException $e) {
            return $this->handleInternal($e, $ctx);
        } catch (Exception $e) {
            return $this->handleUnexpected($e, $ctx, 'Failed to retrieve charging transactions.');
        }
    }

    /**
     * Normalisasi nomor telepon ke format baku internasional Indonesia (628...).
     */
    protected function normalizePhone(?string $phone): ?string
    {
        if (empty($phone)) {
            return null;
        }

        $clean = preg_replace('/[^\d]/', '', $phone);
        if (empty($clean)) {
            return null;
        }

        if (str_starts_with($clean, '0')) {
            $clean = '62' . substr($clean, 1);
        } elseif (str_starts_with($clean, '8')) {
            $clean = '62' . $clean;
        }

        return $clean;
    }

    /**
     * Normalisasi plat nomor (kapitalisasi huruf dan hilangkan spasi ganda).
     */
    protected function normalizePlateNumber(?string $plate): ?string
    {
        if (empty($plate)) {
            return null;
        }

        $plate = strtoupper(trim($plate));
        return preg_replace('/\s+/', ' ', $plate);
    }
}
