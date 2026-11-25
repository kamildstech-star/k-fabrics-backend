<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $primaryImg = $this->images->firstWhere('is_primary', true) ?? $this->images->first();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'size_chart_type' => $this->size_chart_type,
            'size_chart' => array_values($this->size_chart ?? []),
            'fit_notes' => $this->fit_notes,
            'care_instructions' => $this->care_instructions,
            'fabric_type' => $this->fabric_type,
            'piece_type' => $this->piece_type,
            'base_price' => (float) $this->base_price,
            'sale_price' => $this->sale_price !== null ? (float) $this->sale_price : null,
            'custom_fitting_fee' => max(0, (int) config('checkout.custom_fitting_fee_minor')) / 100,
            'is_featured' => (bool) $this->is_featured,
            'status' => $this->status,
            'category' => [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ],
            'primary_image' => $primaryImg?->image_url,
            'images' => ProductImageResource::collection($this->whenLoaded('images')),
            'variants' => ProductVariantResource::collection($this->whenLoaded('variants')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
