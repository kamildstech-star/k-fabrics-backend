<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'user_id',
        'customer_name',
        'customer_phone',
        'customer_email',
        'shipping_address',
        'city',
        'subtotal',
        'shipping_fee',
        'discount_amount',
        'total_amount',
        'payment_method',
        'payment_status',
        'order_status',
        'courier_name',
        'tracking_number',
        'notes',
        'phone_verified_at',
        'confirmation_channel',
        'confirmed_at',
        'cod_confirmation_expires_at',
        'cancelled_reason',
        'idempotency_hash',
        'checkout_hash',
        'coupon_id',
        'advance_due_minor',
    ];

    protected $hidden = ['idempotency_hash', 'checkout_hash', 'tracking_nonce'];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'advance_due_minor' => 'integer',
            'shipping_fee' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'phone_verified_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'cod_confirmation_expires_at' => 'datetime',
        ];
    }

    public function scopePendingCodConfirmation(Builder $query): Builder
    {
        return $query->where('payment_method', 'cod')
            ->where('order_status', 'pending')
            ->whereNull('confirmed_at')
            ->where('confirmation_channel', 'none');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(OrderPayment::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function stitchingRequests(): HasMany
    {
        return $this->hasMany(StitchingRequest::class);
    }
}
