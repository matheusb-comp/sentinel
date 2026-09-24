<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Validates that a record of the given model exists with the given uuid.
 *
 * The lookup goes through the model's query, so its global scopes apply, which
 * Rule::exists does not do. The format is checked first because Postgres
 * rejects a non-uuid value for a uuid column.
 *
 * Constraints are added the way Rule::exists takes them, and narrow what counts:
 * a record they leave out reads as one that does not exist.
 */
class ExistsByUuid implements ValidationRule
{
    /** @var list<Closure(Builder<Model>): mixed> */
    private array $constraints = [];

    /**
     * @param  class-string<Model>  $model
     */
    public function __construct(private string $model) {}

    public function whereNull(string $column): self
    {
        $this->constraints[] = fn (Builder $query) => $query->whereNull($column);

        return $this;
    }

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

        $query = $this->model::where('uuid', $value);

        foreach ($this->constraints as $constraint) {
            $constraint($query);
        }

        if (! $query->exists()) {
            $fail('validation.exists')->translate();
        }
    }
}
