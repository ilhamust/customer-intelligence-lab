# Backend Context: Jarvis (Laravel)

File ini adalah **referensi gaya dan pola** untuk backend Jarvis. Kode contoh diambil dari codebase lain yang hanya dipakai sebagai referensi, lalu disesuaikan.

**Aturan:** jangan menyalin kode atau domain dari codebase referensi selain yang tercantum di bagian "Dipakai". Jika sebuah pola tidak relevan dengan kebutuhan Jarvis, tidak perlu diterapkan.

---

## 1. Keputusan

| Hal | Keputusan |
|---|---|
| Framework | Laravel (PHP), REST API murni di `/api/v1` |
| Auth staf | **Laravel Sanctum** (`auth:sanctum`), bukan JWT |
| Auth sistem luar (CMS palsu, simulator) | Middleware `ApiKeyAuth`, terpisah dari Sanctum |
| Webhook masuk | Verifikasi HMAC lewat middleware `VerifyHmacSignature`, plus idempotency |
| Pola kode | Controller → Service (Contract → Implement) → Model, input lewat Data (DTO), output lewat Resource |
| Format sukses | `{ data, title, status, code, meta }` |
| Format error | `{ errors: [ { title, details, code, status, meta } ] }` |
| Status validasi gagal (FormRequest) | **400** lewat `ValidationErrorResource` |
| Status pelanggaran aturan bisnis | **422** lewat `UnprocessableEntityException` (contoh: saldo poin kurang, redeem ditolak) |
| Primary key | ULID (`HasUlids`) |
| Dokumentasi API | Opsional (Scribe), bukan prioritas |

---

## 2. Dipakai

| Pola | Dipakai untuk |
|---|---|
| `Data/` (DTO dengan `fromRequest`) | Input webhook charging, registrasi, pesan bot, tool AI |
| Service: `Contracts/` dan `Implements/` | Semua service domain |
| `ServiceHelper` (logging terstruktur) | Penelusuran webhook, bot, dan panggilan AI |
| FormRequest + `failedValidation` | Validasi semua endpoint |
| Hierarki exception (`BaseException` dan turunannya) | Error konsisten, termasuk saat tool AI gagal |
| `ErrorResource`, `ValidationErrorResource`, `InternalErrorResource` | Format error |
| `DataResource`, `BaseCollection`, `HasApiMeta` | Format sukses dan pagination |
| `ControllerHelpers` | Penanganan error terpusat di controller |
| `EnsureJsonRequest` | Semua route API |
| `HasUlidsTool` | ULID |
| `Jobs/` dan `Console/Commands/` | Antrean pesan, notifikasi, deteksi inaktivitas |
| Domain `Chats/` (Conversation, Message) | Dasar tabel `conversations` dan `messages`. Tanpa event real-time, simulator memakai polling. |

---

## 3. Struktur folder `app/` untuk Jarvis

```
app/
├── Console/Commands/
│   ├── DetectInactiveCustomers.php
│   └── RunEvals.php
├── Constants/
├── Data/
│   ├── Auth/ Customers/ Charging/ Loyalty/ Bot/ Ai/ General/   # General: PaginationData, SearchData
├── Enum/
│   ├── Customers/   → DriverType, CustomerStatus
│   ├── Bot/         → ConversationStep, MessageDirection
│   ├── Alerts/      → UrgencyLevel, AlertStatus
│   └── Charging/    → ChargerStatus
├── Exceptions/      # BaseException + turunan (lihat bagian 5)
├── Http/
│   ├── Controllers/
│   │   ├── Auth/ Customers/ Charging/ Loyalty/ Bot/ Staff/ Webhooks/
│   ├── Middleware/  → EnsureJsonRequest, EnsureRole, ApiKeyAuth, VerifyHmacSignature
│   ├── Requests/    # satu folder per domain
│   └── Resources/
│       ├── Api/     → DataResource, BaseCollection, ErrorResource, InternalErrorResource, ValidationErrorResource
│       └── {Customers, Loyalty, Alerts, Tickets, ...}/
├── Jobs/            → ProcessIncomingMessage, SendOutgoingMessage, NotifyPoints
├── Models/          # HasUlids
│   ├── Auth/ Customers/ Charging/ Loyalty/ Bot/ Alerts/ Knowledge/
├── Service/
│   ├── Auth/ Customers/ Charging/ Loyalty/ Alerts/ Tickets/
│   │     └── {Contracts, Implements}/
│   ├── Bot/         # flow registrasi, ChannelAdapter (SimulatorAdapter, GowaAdapter)
│   └── Ai/          # Agent, ContextBuilder, Tools/, Guardrail, KnowledgeBase, LlmClient (GeminiClient, FakeLlmClient)
└── Traits/          → ApiHelperResponse, HasApiMeta, ControllerHelpers, ServiceHelper
```

Pemetaan nama dari codebase referensi: `Provider` menjadi `Customer`, `Property` menjadi `Station`, domain `Chats` menjadi `Bot` (conversations dan messages), `Auth/User` menjadi staf internal.

---

## 4. Pola kode

### 4.1 Controller

Pola: `use ControllerHelpers`, inject Service contract lewat constructor, kembalikan Resource atau ErrorResource.

```php
<?php
namespace App\Http\Controllers\Customers;

use App\Exceptions\{ForbiddenRequestException, NotFoundException};
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\{ErrorResource, InternalErrorResource};
use App\Http\Resources\Customers\CustomerResource;
use App\Service\Customers\Contracts\CustomerService;
use App\Traits\ControllerHelpers;
use Illuminate\Log\Logger;
use Illuminate\Routing\Controllers\{HasMiddleware, Middleware};

class CustomerController extends Controller implements HasMiddleware
{
    use ControllerHelpers;

    public function __construct(
        protected Logger $logger,
        protected CustomerService $customerService,
    ) {}

    protected function getLog(): Logger { return $this->logger; }

    public static function middleware(): array
    {
        return [new Middleware('auth:sanctum')];
    }

    public function show(string $id): CustomerResource|ErrorResource|InternalErrorResource
    {
        $ctx = __METHOD__;
        try {
            return $this->customerService->getById($id)
                ->setMeta(title: 'Successfully get customer details');
        } catch (NotFoundException $e)         { return $this->handleNotFound($e, $ctx);
        } catch (ForbiddenRequestException $e) { return $this->handleForbidden($e, $ctx);
        } catch (\Exception $e)                { return $this->handleUnexpected($e, $ctx, 'Failed to get customer.'); }
    }
}
```

### 4.2 FormRequest (contoh: webhook charging)

```php
<?php
namespace App\Http\Requests\Charging;

use App\Http\Resources\Api\ValidationErrorResource;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ChargingWebhookRequest extends FormRequest
{
    public function authorize(): bool { return true; }   // autentikasi dilakukan middleware

    public function rules(): array
    {
        return [
            'external_id'    => ['required', 'string', 'max:100'],
            'station_code'   => ['required', 'string', 'exists:stations,code'],
            'charger_code'   => ['required', 'string', 'max:50'],
            'customer_phone' => ['nullable', 'string', 'max:20'],
            'kwh'            => ['required', 'numeric', 'gt:0'],
            'amount'         => ['required', 'numeric', 'min:0'],
            'start_at'       => ['required', 'date'],
            'end_at'         => ['required', 'date', 'after_or_equal:start_at'],
        ];
    }

    public function messages(): array
    {
        return [
            'external_id.required' => 'The external transaction ID is required.',
            'kwh.gt'               => 'kWh must be greater than 0.',
        ];
    }

    /** Override: gagal validasi menjadi ValidationErrorResource (400) */
    public function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            (new ValidationErrorResource($validator))->response()
        );
    }
}
```

### 4.3 Data (DTO)

```php
<?php
namespace App\Data\Charging;

use App\Http\Requests\Charging\ChargingWebhookRequest;

final class ChargingWebhookData
{
    public function __construct(
        public readonly string $externalId,
        public readonly string $stationCode,
        public readonly string $chargerCode,
        public readonly ?string $customerPhone,
        public readonly float $kwh,
        public readonly float $amount,
        public readonly string $startAt,
        public readonly string $endAt,
    ) {}

    public static function fromRequest(ChargingWebhookRequest $request): self
    {
        $v = $request->validated();
        return new self(
            externalId:    $v['external_id'],
            stationCode:   $v['station_code'],
            chargerCode:   $v['charger_code'],
            customerPhone: $v['customer_phone'] ?? null,
            kwh:           (float) $v['kwh'],
            amount:        (float) $v['amount'],
            startAt:       $v['start_at'],
            endAt:         $v['end_at'],
        );
    }
}
```

### 4.4 Service: Contract dan Implement

```php
<?php
namespace App\Service\Loyalty\Contracts;

use App\Http\Resources\Api\DataResource;

interface LoyaltyService
{
    public function getBalance(string $customerId): DataResource;
    public function redeem(string $customerId, string $rewardId): DataResource;
}
```

```php
<?php
namespace App\Service\Loyalty\Implements;

use App\Exceptions\{NotFoundException, UnprocessableEntityException};
use App\Http\Resources\Api\DataResource;
use App\Models\Customers\Customer;
use App\Models\Loyalty\{LoyaltyLedger, Reward};
use App\Service\Loyalty\Contracts\LoyaltyService;
use App\Traits\ServiceHelper;
use Illuminate\Log\Logger;
use Illuminate\Support\Facades\DB;

class LoyaltyServiceImpl implements LoyaltyService
{
    use ServiceHelper;

    public function __construct(protected Logger $logger) {}
    protected function getLog(): Logger { return $this->logger; }

    public function redeem(string $customerId, string $rewardId): DataResource
    {
        $m = __METHOD__;
        return DB::transaction(function () use ($customerId, $rewardId, $m) {
            $customer = Customer::lockForUpdate()->find($customerId)
                ?? throw new NotFoundException('Customer not found.');
            $reward = Reward::find($rewardId)
                ?? throw new NotFoundException('Reward not found.');

            $balance = (int) LoyaltyLedger::where('customer_id', $customerId)->sum('delta');
            if ($balance < $reward->cost_points) {
                throw new UnprocessableEntityException('Insufficient points.');   // 422: aturan bisnis
            }

            LoyaltyLedger::create([
                'customer_id' => $customerId,
                'delta'       => -$reward->cost_points,
                'reason'      => "redeem:{$reward->id}",
            ]);

            $this->logInfo($m, 'Reward redeemed', ['customer' => $customerId, 'reward' => $rewardId]);
            return new DataResource(['balance' => $balance - $reward->cost_points]);
        });
    }
}
```

Binding di `AppServiceProvider`:

```php
$this->app->bind(LoyaltyService::class, LoyaltyServiceImpl::class);
```

---

## 5. Hierarki exception

```
BaseException (abstract): title, statusCode()
├── BadRequestException             400
├── UnauthorizedException           401
├── ForbiddenRequestException       403
├── NotFoundException               404
├── UnprocessableEntityException    422   (aturan bisnis dilanggar)
└── InternalServerException         500
```

Controller menangkap exception ini dan mengubahnya lewat `ControllerHelpers` (`handleNotFound`, `handleForbidden`, `handleUnprocessable`, `handleUnauthorized`, `handleInternal`, `handleUnexpected`). Error 500 tidak boleh membawa detail internal di production.

---

## 6. Format response

Sukses:

```json
{
  "data": { "id": "01JQMZR7X...", "name": "Made" },
  "title": "Successfully get customer details",
  "status": "STATUS_OK",
  "code": 200,
  "meta": {}
}
```

Error:

```json
{
  "errors": [{
    "title": "Not found.",
    "details": "Customer with id ... not found.",
    "code": 404,
    "status": "STATUS_NOT_FOUND",
    "meta": []
  }]
}
```

Frontend menampilkan `errors[0].details` lewat toast.

---

## 7. Alur request ke response

```
Request → EnsureJsonRequest → (auth:sanctum | ApiKeyAuth | VerifyHmacSignature)
        → FormRequest (gagal → ValidationErrorResource 400)
        → Controller → Data::fromRequest()
        → Service (Contract → Implement) → Model
        → Resource->setMeta(title: '...') → JSON
Exception di Service → ditangkap Controller → ErrorResource / InternalErrorResource
```

---

## 8. Bagian baru yang TIDAK ada di codebase referensi

Dibangun mengikuti pola di atas:

1. `VerifyHmacSignature`: verifikasi tanda tangan webhook (GOWA dan CMS). Tolak dengan 401 jika tidak valid.
2. Idempotency webhook charging: kolom `external_id` unik, request ulang dibalas sukses tanpa data ganda.
3. Ledger poin: setiap perubahan poin adalah satu baris di `loyalty_ledger`, saldo adalah `SUM`. Redeem di dalam database transaction.
4. Bot: state machine registrasi, `ChannelAdapter` (SimulatorAdapter, GowaAdapter), jobs di Redis queue.
5. AI: `LlmClient` (GeminiClient dan FakeLlmClient), tool calling, `ContextBuilder`, guard rail, knowledge base.
6. Alert dan tiket: jalur cepat untuk kasus urgent, pengingat lewat scheduler.

Aturan khusus Bot dan AI:

1. Identitas customer selalu diambil dari sesi (nomor pengirim), bukan dari argumen yang dibuat AI.
2. LLM tidak menebak poin, harga, atau status charger. Semuanya lewat tool atau knowledge base.
3. Tool awal bersifat read-only plus pembuatan tiket dan alert. Tool yang mengubah data wajib konfirmasi dari customer.