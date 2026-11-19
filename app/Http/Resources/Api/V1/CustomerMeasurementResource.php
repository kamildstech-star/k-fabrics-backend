<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerMeasurementResource extends JsonResource
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
            'user_id' => $this->user_id,
            'profile_name' => $this->profile_name,
            'unit' => $this->unit,
            'shirt_length' => $this->shirt_length !== null ? (float) $this->shirt_length : null,
            'shoulder' => $this->shoulder !== null ? (float) $this->shoulder : null,
            'chest' => $this->chest !== null ? (float) $this->chest : null,
            'waist' => $this->waist !== null ? (float) $this->waist : null,
            'hips' => $this->hips !== null ? (float) $this->hips : null,
            'sleeve_length' => $this->sleeve_length !== null ? (float) $this->sleeve_length : null,
            'armhole' => $this->armhole !== null ? (float) $this->armhole : null,
            'daman' => $this->daman !== null ? (float) $this->daman : null,
            'neck_front' => $this->neck_front !== null ? (float) $this->neck_front : null,
            'neck_back' => $this->neck_back !== null ? (float) $this->neck_back : null,
            'trouser_length' => $this->trouser_length !== null ? (float) $this->trouser_length : null,
            'trouser_waist' => $this->trouser_waist !== null ? (float) $this->trouser_waist : null,
            'pancha' => $this->pancha !== null ? (float) $this->pancha : null,
            'extra_notes' => $this->extra_notes,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
