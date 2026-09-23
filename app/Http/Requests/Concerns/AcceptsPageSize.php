<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The `per_page` of a paginated listing, with its bounds and its default in
 * config/pagination.php.
 *
 * @mixin FormRequest
 */
trait AcceptsPageSize
{
    /**
     * @return array<string, array<mixed>>
     */
    protected function pageSizeRules(): array
    {
        return [
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.config('pagination.max_per_page')],
        ];
    }

    public function perPage(): int
    {
        /** @var FormRequest $this */
        return $this->filled('per_page')
            ? $this->integer('per_page')
            : config('pagination.per_page');
    }
}
