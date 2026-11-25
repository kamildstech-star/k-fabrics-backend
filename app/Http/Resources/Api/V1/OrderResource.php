<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
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
            'order_number' => $this->order_number,
            'customer_name' => $this->customer_name,
            'customer_phone' => $this->customer_phone,
            'customer_email' => $this->customer_email,
            'shipping_address' => $this->shipping_address,
            'city' => $this->city,
            'subtotal' => (float) $this->subtotal,
            'shipping_fee' => (float) $this->shipping_fee,
            'discount_amount' => (float) $this->discount_amount,
            'total_amount' => (float) $this->total_amount,
            'advance_due' => $this->advance_due_minor / 100,
            'payment_method' => $this->payment_method,
            'payment_status' => $this->payment_status,
            'order_status' => $this->order_status,
            'courier_name' => $this->courier_name,
            'tracking_number' => $this->tracking_number,
            'phone_verified_at' => $this->phone_verified_at?->toISOString(),
            'confirmation_channel' => $this->confirmation_channel,
            'confirmed_at' => $this->confirmed_at?->toISOString(),
            'cancelled_reason' => $this->cancelled_reason,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'stitching_requests' => StitchingRequestResource::collection($this->whenLoaded('stitchingRequests')),
        ];
    }
}
