<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderPayment extends Model
{
    protected $fillable = ['order_id', 'recorded_by', 'kind', 'amount_minor', 'reference', 'received_at'];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'received_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
