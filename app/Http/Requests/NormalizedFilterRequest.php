<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator;

/** Invalid GET filters are ignored individually rather than causing redirect loops. */
abstract class NormalizedFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Record and role authorization remains in controllers and policies.
    }

    protected function prepareForValidation(): void
    {
        $clean = [];

        foreach ($this->rules() as $name => $rules) {
            $value = $this->query($name);
            if (is_string($value)) {
                $value = trim($value);
            }
            if ($value !== null && $value !== ''
                && Validator::make([$name => $value], [$name => $rules])->passes()) {
                $clean[$name] = $value;
            }
        }

        // Pagination must retain only normalized parameters, including safe page numbers.
        $this->query->replace($clean);
    }

    protected function paginationRules(): array
    {
        return ['page' => ['nullable', 'integer', 'min:1', 'max:1000000']];
    }
}
