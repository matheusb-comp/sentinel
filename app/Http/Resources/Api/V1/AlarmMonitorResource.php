<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AlarmMonitorResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * `pending_since` is what tells a value already on the wrong side from one
     * that has been there long enough to alarm.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'status' => $this->status,
            'status_since' => $this->status_since?->format('c'),
            'pending_since' => $this->pending_since?->format('c'),
            'sensor' => new SensorResource($this->whenLoaded('sensor')),
        ];
    }
}
