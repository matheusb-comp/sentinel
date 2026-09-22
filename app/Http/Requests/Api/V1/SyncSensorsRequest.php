<?php

namespace App\Http\Requests\Api\V1;

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
    public const MAX_SENSORS = 1000;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'sensors' => ['required', 'list', 'max:'.self::MAX_SENSORS],
            'sensors.*.key' => ['required', 'string', 'max:255', 'distinct:strict'],
            'sensors.*.description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
