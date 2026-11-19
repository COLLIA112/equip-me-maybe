<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReturnReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage', $this->route('reservation'));
    }

    public function rules(): array
    {
        return [
            'return_condition' => ['nullable', Rule::in(['good', 'damaged', 'missing_parts'])],
            'return_notes' => ['nullable', 'string', 'max:2000', Rule::requiredIf(in_array($this->input('return_condition'), ['damaged', 'missing_parts'], true))],
        ];
    }
}
