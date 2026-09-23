<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A device registration: the key that identifies it for the client, the label
 * a person gives it, and the sensors it has.
 *
 * `sensors` absent leaves the device's sensors as they are. An empty list is
 * refused, since reconciling against it would archive every one of them.
 */
class RegisterDeviceRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'key' => ['required', 'string', 'max:255'],
            'label' => ['nullable', 'string', 'max:255'],
            'sensors' => ['nullable', 'list', 'min:1', 'max:'.config('ingestion.max_sensors_per_device')],
            'sensors.*.key' => ['required', 'string', 'max:255', 'distinct:strict'],
            'sensors.*.description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
