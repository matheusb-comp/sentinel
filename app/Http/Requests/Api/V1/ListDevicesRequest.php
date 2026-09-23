<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\AcceptsPageSize;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The filter and the page size of the device listing.
 *
 * An absent `archived` means every device, which is not what `0` means. The
 * `boolean` rule takes `1` and `0`, never `true` and `false` as text.
 */
class ListDevicesRequest extends FormRequest
{
    use AcceptsPageSize;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'archived' => ['nullable', 'boolean'],
            ...$this->pageSizeRules(),
        ];
    }
}
