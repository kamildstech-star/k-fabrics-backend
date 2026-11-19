<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerMeasurement extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'profile_name',
        'unit',
        'shirt_length',
        'shoulder',
        'chest',
        'waist',
        'hips',
        'sleeve_length',
        'armhole',
        'daman',
        'neck_front',
        'neck_back',
        'trouser_length',
        'trouser_waist',
        'pancha',
        'extra_notes',
    ];

    protected function casts(): array
    {
        return [
            'shirt_length' => 'decimal:2',
            'shoulder' => 'decimal:2',
            'chest' => 'decimal:2',
            'waist' => 'decimal:2',
            'hips' => 'decimal:2',
            'sleeve_length' => 'decimal:2',
            'armhole' => 'decimal:2',
            'daman' => 'decimal:2',
            'neck_front' => 'decimal:2',
            'neck_back' => 'decimal:2',
            'trouser_length' => 'decimal:2',
            'trouser_waist' => 'decimal:2',
            'pancha' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function stitchingRequests(): HasMany
    {
        return $this->hasMany(StitchingRequest::class, 'measurement_id');
    }
}
