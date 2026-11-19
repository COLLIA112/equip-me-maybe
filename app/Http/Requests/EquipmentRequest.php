<?php

namespace App\Http\Requests;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EquipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // EquipmentPolicy is enforced in the controller
    }

    public function rules(): array
    {
        $equipment = $this->route('equipment');

        return [
            // A department head has no lab of their own, so they must choose one when adding equipment.
            // (A lab manager's lab always comes from their account.)
            'lab_id' => [
                Rule::requiredIf(fn () => ! $equipment && $this->user()?->hasRole(Role::DepartmentHead)),
                'nullable',
                'exists:labs,id',
            ],
            'name' => ['required', 'string', 'max:150'],
            'serial_number' => ['required', 'string', 'max:100', Rule::unique('equipment', 'serial_number')->ignore($equipment?->id)],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['exists:tags,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'lab_id.required' => 'Choose the lab this equipment belongs to.',
        ];
    }
}
