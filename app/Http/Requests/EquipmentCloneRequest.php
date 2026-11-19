<?php

namespace App\Http\Requests;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EquipmentCloneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(Role::LabManager)
            && $this->user()->managesLab($this->route('equipment')?->lab_id);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'serial_number' => ['required', 'string', 'max:100', Rule::unique('equipment', 'serial_number')],
        ];
    }
}
