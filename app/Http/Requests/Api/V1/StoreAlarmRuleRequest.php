<?php

namespace App\Http\Requests\Api\V1;

use App\Actions\Readings\IngestReadings;
use App\Alarms\AlarmDirection;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAlarmRuleRequest extends FormRequest
{
    /** A year, more than enough for any of the three durations. */
    private const MAX_SECONDS = 31622400;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'label' => ['nullable', 'string', 'max:255'],
            'direction' => ['required', Rule::enum(AlarmDirection::class)],

            // Bounded by what a reading holds, so that the two are comparable,
            // and so that a JSON number too large for a double — which decodes
            // to infinity — is refused instead of stored.
            'threshold' => ['required', 'numeric', 'between:-'.IngestReadings::MAX_MAGNITUDE.','.IngestReadings::MAX_MAGNITUDE],

            'trigger_after' => ['required', 'integer', 'min:0', 'max:'.self::MAX_SECONDS],

            // Absent leaves the column default; null is not a duration.
            'clear_after' => ['integer', 'min:0', 'max:'.self::MAX_SECONDS],

            // Null is meaningful here: it falls back to the config.
            'max_reading_age' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_SECONDS],

            'active' => ['boolean'],
        ];
    }
}
