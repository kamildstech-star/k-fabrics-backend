<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductReviewResource extends JsonResource
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
            'reviewer_name' => $this->reviewer_name,
            'rating' => (int) $this->rating,
            'comment' => $this->comment,
            'photo_url' => $this->is_approved && $this->resource->hasPrivatePhoto()
                ? route('products.reviews.photo', ['review' => $this->id])
                : null,
            'is_verified_purchase' => (bool) $this->is_verified_purchase,
            'moderation_status' => $this->is_approved ? 'published' : 'pending',
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
