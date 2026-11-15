<?php

namespace App\Http\Requests;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LabAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(Role::DepartmentHead)
            && $this->user()->department_id !== null
            && (! $this->route('lab') || $this->user()->headsDepartment($this->route('lab')->department_id));
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150', Rule::unique('labs', 'name')
                ->where('department_id', $this->user()->department_id)->ignore($this->route('lab')?->id)],
            'location' => ['nullable', 'string', 'max:255'],
        ];
    }
}
