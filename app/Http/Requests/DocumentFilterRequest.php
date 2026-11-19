<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class DocumentFilterRequest extends NormalizedFilterRequest
{
    public function rules(): array
    {
        return [
            'required' => ['nullable', Rule::in(['required', 'optional', 'all'])],
            'acknowledged' => ['nullable', Rule::in(['yes', 'no'])],
            'sort' => ['nullable', Rule::in(['title', 'is_required', 'created_at'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ] + $this->paginationRules();
    }
}
