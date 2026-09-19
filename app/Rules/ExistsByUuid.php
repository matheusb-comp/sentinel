<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Validates that a record of the given model exists with the given uuid.
 *
 * The lookup goes through the model's query, so its global scopes apply, which
 * Rule::exists does not do. The format is checked first because Postgres
 * rejects a non-uuid value for a uuid column.
 */
class ExistsByUuid implements ValidationRule
{
    /**
     * @param  class-string<Model>  $model
     */
    public function __construct(private string $model) {}

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! Str::isUuid($value)) {
            $fail('validation.uuid')->translate();

            return;
        }

        if (! $this->model::where('uuid', $value)->exists()) {
            $fail('validation.exists')->translate();
        }
    }
}
