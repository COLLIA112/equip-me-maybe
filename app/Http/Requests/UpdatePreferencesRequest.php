<?php

namespace App\Http\Requests;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && ! $this->user()->hasRole(Role::SecurityAuditor);
    }

    public function rules(): array
    {
        return [
            'theme' => ['required', 'string', Rule::in(['light', 'dark', 'system'])],
            'remember_filters' => ['sometimes', 'boolean'],
            'lists' => ['prohibited'],
        ];
    }
}
