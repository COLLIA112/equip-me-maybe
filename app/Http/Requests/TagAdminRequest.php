<?php

namespace App\Http\Requests;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TagAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(Role::LabManager, Role::DepartmentHead) ?? false;
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:100', Rule::unique('tags', 'name')->ignore($this->route('tag')?->id)]];
    }
}
