<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StitchingRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'order_item_id',
        'stitching_service_id',
        'measurement_id',
        'measurement_snapshot',
        'request_type',
        'fabric_delivery_method',
        'fabric_received_at',
        'design_reference_image',
        'neck_style',
        'sleeve_style',
        'trouser_style',
        'special_instructions',
        'stitching_fee',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'fabric_received_at' => 'datetime',
            'stitching_fee' => 'decimal:2',
            'measurement_snapshot' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function stitchingService(): BelongsTo
    {
        return $this->belongsTo(StitchingService::class, 'stitching_service_id');
    }

    public function orderMeasurement(): ?CustomerMeasurement
    {
        return $this->measurement_snapshot !== null
            ? new CustomerMeasurement($this->measurement_snapshot)
            : $this->measurement;
    }

    public function measurement(): BelongsTo
    {
        return $this->belongsTo(CustomerMeasurement::class, 'measurement_id');
    }
}
