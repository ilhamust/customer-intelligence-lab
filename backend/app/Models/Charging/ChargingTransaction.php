<?php

namespace App\Models\Charging;

use App\Models\Bot\Customer;
use App\Models\Loyalty\PointsLedger;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ChargingTransaction extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'charging_transactions';

    protected $fillable = [
        'external_id',
        'customer_id',
        'phone',
        'plate_number',
        'station_code',
        'station_name',
        'charger_code',
        'charger_type',
        'kwh',
        'amount',
        'duration_minutes',
        'started_at',
        'finished_at',
        'status',
        'points_awarded',
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
            'kwh' => 'decimal:2',
            'amount' => 'decimal:2',
            'duration_minutes' => 'integer',
            'points_awarded' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    /**
     * Customer associated with this charging transaction.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'id');
    }

    /**
     * Points ledger record generated from this transaction.
     */
    public function pointsLedger(): HasOne
    {
        return $this->hasOne(PointsLedger::class, 'transaction_id', 'id');
    }
}
