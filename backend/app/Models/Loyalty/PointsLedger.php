<?php

namespace App\Models\Loyalty;

use App\Models\Bot\Customer;
use App\Models\Charging\ChargingTransaction;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PointsLedger extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'points_ledger';

    public $timestamps = false;

    protected $fillable = [
        'customer_id',
        'transaction_id',
        'type',
        'points',
        'balance_after',
        'description',
        'created_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'points' => 'integer',
            'balance_after' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Customer owning this points ledger entry.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'id');
    }

    /**
     * Charging transaction related to this points ledger entry.
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(ChargingTransaction::class, 'transaction_id', 'id');
    }
}
