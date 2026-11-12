<?php

namespace App\Http\Requests;

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
            'lab_id' => ['nullable', 'exists:labs,id'],
            'name' => ['required', 'string', 'max:150'],
            'serial_number' => ['required', 'string', 'max:100', Rule::unique('equipment', 'serial_number')->ignore($equipment?->id)],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['exists:tags,id'],
        ];
    }
}
