<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UnwatchSensorsRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'sensor_uuids' => ['required', 'list', 'min:1', 'max:'.config('alarms.max_sensors_per_request')],

            // Only the format: a uuid that names nothing has nothing to stop
            // watching. The format still matters, because Postgres errors on a
            // malformed value for a uuid column.
            'sensor_uuids.*' => ['required', 'uuid'],
        ];
    }
}
