<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\AcceptsPageSize;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ListAlarmRulesRequest extends FormRequest
{
    use AcceptsPageSize;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->pageSizeRules();
    }
}
