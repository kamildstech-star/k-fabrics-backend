<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'sku',
        'size',
        'color',
        'color_hex',
        'price_override',
        'stock_quantity',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price_override' => 'decimal:2',
            'stock_quantity' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function scopeLowStock(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where('size', '!=', 'Custom')
            ->where('stock_quantity', '<=', 3)
            ->whereHas('product', fn (Builder $product) => $product->where('status', 'active'));
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'variant_id');
    }

    public function stockAlerts(): HasMany
    {
        return $this->hasMany(StockAlert::class, 'product_variant_id');
    }
}
