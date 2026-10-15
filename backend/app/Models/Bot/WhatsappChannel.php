<?php

namespace App\Models\Bot;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsappChannel extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'whatsapp_channels';

    protected $fillable = [
        'device_id',
        'name',
        'phone_number',
        'jid',
        'status',
        'is_active',
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
            'is_active' => 'boolean',
            'meta' => 'array',
        ];
    }
}
