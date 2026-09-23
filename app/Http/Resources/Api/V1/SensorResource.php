<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SensorResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'key' => $this->key,
            'description' => $this->description,
            'label' => $this->label,
            'unit' => $this->unit,
            'expected_interval' => $this->expected_interval,
            'archived_at' => $this->archived_at?->format('c'),
        ];
    }
}
