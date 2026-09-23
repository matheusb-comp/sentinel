<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeviceResource extends JsonResource
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
            'label' => $this->label,
            'archived_at' => $this->archived_at?->format('c'),
            'sensors' => SensorResource::collection($this->whenLoaded('sensors')),
        ];
    }
}
