<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Sensor;
use App\Rules\ExistsByUuid;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class WatchSensorsRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'sensor_uuids' => ['required', 'list', 'min:1', 'max:'.config('alarms.max_sensors_per_request')],

            // ExistsByUuid queries through the model, so the tenant scope answers
            // for the company: a sensor of another one reads as one that does not
            // exist. An archived one reads the same way, because ingestion
            // refuses its readings, so watching it could never move.
            'sensor_uuids.*' => [
                'required',
                'string',
                (new ExistsByUuid(Sensor::class))->whereNull('archived_at'),
            ],
        ];
    }
}
