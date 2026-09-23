<?php

namespace App\Http\Requests\Ingest\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The full list of sensors a device declares.
 *
 * An empty list is refused here although SyncDeviceSensors accepts one: it would
 * archive every sensor of the device.
 */
class SyncSensorsRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'sensors' => ['required', 'list', 'max:'.config('ingestion.max_sensors_per_device')],
            'sensors.*.key' => ['required', 'string', 'max:255', 'distinct:strict'],
            'sensors.*.description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
