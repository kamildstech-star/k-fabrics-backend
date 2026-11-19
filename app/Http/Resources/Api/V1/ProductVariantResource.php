<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductVariantResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'size' => $this->size,
            'color' => $this->color,
            'color_hex' => $this->color_hex,
            'price_override' => $this->price_override !== null ? (float) $this->price_override : null,
            'stock_quantity' => (int) $this->stock_quantity,
            'is_active' => (bool) $this->is_active,
        ];
    }
}
