<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $primaryImg = $this->images->firstWhere('is_primary', true) ?? $this->images->first();
        $hoverImg = $this->images->where('id', '!=', $primaryImg?->id)->first() ?? $primaryImg;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'fabric_type' => $this->fabric_type,
            'piece_type' => $this->piece_type,
            'base_price' => (float) $this->base_price,
            'sale_price' => $this->sale_price !== null ? (float) $this->sale_price : null,
            'is_featured' => (bool) $this->is_featured,
            'status' => $this->status,
            'category' => [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ],
            'primary_image' => $primaryImg?->image_url,
            'hover_image' => $hoverImg?->image_url,
            'images' => ProductImageResource::collection($this->whenLoaded('images')),
            'variants' => ProductVariantResource::collection($this->whenLoaded('variants')),
        ];
    }
}
