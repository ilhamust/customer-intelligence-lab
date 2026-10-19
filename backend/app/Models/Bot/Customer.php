<?php

namespace App\Models\Bot;

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
        'driver_type',
        'residential_area',
        'primary_route',
        'points_balance',
        'status',
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
