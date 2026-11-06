<?php

namespace App\Models\Staff;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StationStaff extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'station_staff';

    protected $fillable = [
        'name',
        'phone',
        'role',
        'station_name',
        'is_active',
        'is_on_duty',
        'notes',
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
            'is_on_duty' => 'boolean',
        ];
    }
}

