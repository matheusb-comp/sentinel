<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AlarmRuleResource extends JsonResource
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
            'label' => $this->label,
            'direction' => $this->direction,
            'threshold' => $this->threshold,
            'trigger_after' => $this->trigger_after,
            'clear_after' => $this->clear_after,
            'max_reading_age' => $this->max_reading_age,
            'active' => $this->active,
        ];
    }
}
