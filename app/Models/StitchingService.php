<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StitchingService extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'base_price',
        'estimated_days',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:2',
            'estimated_days' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function stitchingRequests(): HasMany
    {
        return $this->hasMany(StitchingRequest::class);
    }
}
