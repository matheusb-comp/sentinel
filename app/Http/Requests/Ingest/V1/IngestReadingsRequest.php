<?php

namespace App\Http\Requests\Ingest\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A batch of readings. Only the envelope is validated here: IngestReadings
 * validates each item and leaves an invalid one out, reporting its position.
 */
class IngestReadingsRequest extends FormRequest
{
    public const MAX_READINGS = 5000;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'readings' => ['required', 'list', 'max:'.self::MAX_READINGS],
        ];
    }
}
