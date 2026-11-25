<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StitchingRequestResource extends JsonResource
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
            'order_id' => $this->order_id,
            'order_item_id' => $this->order_item_id,
            'stitching_service_id' => $this->stitching_service_id,
            'measurement_id' => $this->measurement_id,
            'request_type' => $this->request_type,
            'measurement_method' => $this->measurement_method,
            'fabric_delivery_method' => $this->fabric_delivery_method,
            'fabric_received_at' => $this->fabric_received_at?->toISOString(),
            'neck_style' => $this->neck_style,
            'sleeve_style' => $this->sleeve_style,
            'trouser_style' => $this->trouser_style,
            'special_instructions' => $this->special_instructions,
            'stitching_fee' => (float) $this->stitching_fee,
            'status' => $this->status,
            'service' => new StitchingServiceResource($this->whenLoaded('stitchingService')),
            'measurement' => $this->when(
                $this->measurement_snapshot !== null || $this->resource->relationLoaded('measurement'),
                fn () => $this->resource->orderMeasurement() ? new CustomerMeasurementResource($this->resource->orderMeasurement()) : null,
            ),
        ];
    }
}
