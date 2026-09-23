<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeviceTokenResource extends JsonResource
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
            'created_at' => $this->created_at?->format('c'),
            'last_used_at' => $this->last_used_at?->format('c'),
            'expires_at' => $this->expires_at?->format('c'),
        ];
    }
}
