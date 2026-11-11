<?php

namespace App\Models\Bot;

use App\Models\Charging\ChargingTransaction;
use App\Models\Loyalty\PointsLedger;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'customers';

    protected $fillable = [
        'customer_code',
        'name',
        'phone',
        'vehicle',
        'plate_number',
        'driver_type',
        'residential_area',
        'primary_route',
        'points_balance',
        'status',
        'is_marketing_consent',
        'consented_at',
        'opted_out_at',
        'last_charged_at',
        'inactive_detected_at',
        'last_retention_sent_at',
        'meta',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'points_balance' => 'integer',
            'is_marketing_consent' => 'boolean',
            'consented_at' => 'datetime',
            'opted_out_at' => 'datetime',
            'last_charged_at' => 'datetime',
            'inactive_detected_at' => 'datetime',
            'last_retention_sent_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    /**
     * Conversations associated with this customer.
     */
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'customer_id', 'id');
    }

    /**
     * Tickets associated with this customer.
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'customer_id', 'id')->orderBy('created_at', 'desc');
    }

    /**
     * Charging transactions completed by this customer.
     */
    public function chargingTransactions(): HasMany
    {
        return $this->hasMany(ChargingTransaction::class, 'customer_id', 'id')->orderBy('finished_at', 'desc');
    }

    /**
     * Points ledger history for this customer.
     */
    public function pointsLedgers(): HasMany
    {
        return $this->hasMany(PointsLedger::class, 'customer_id', 'id')->orderBy('created_at', 'desc');
    }

    /**
     * Generate a unique customer code in the format JRV-XXXX (e.g. JRV-8821).
     */
    public static function generateCustomerCode(): string
    {
        do {
            $code = 'JRV-'.mt_rand(1000, 9999);
        } while (static::where('customer_code', $code)->exists());

        return $code;
    }
}
