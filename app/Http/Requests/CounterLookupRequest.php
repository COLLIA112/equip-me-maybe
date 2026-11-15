<?php

namespace App\Http\Requests;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;

class CounterLookupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole(Role::LabManager);
    }

    public function rules(): array
    {
        return ['code' => [$this->isMethod('post') ? 'required' : 'nullable', 'string', 'max:100', 'regex:/^(?:EQ-[A-Za-z0-9-]+|#?[0-9]+)$/']];
    }
}
