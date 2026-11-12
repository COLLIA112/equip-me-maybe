<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMaintenanceLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // EquipmentPolicy@update is enforced in the controller
    }

    public function rules(): array
    {
        return [
            'equipment_id' => ['required', 'exists:equipment,id'],
            'issue' => ['required', 'string', 'max:2000'],
        ];
    }
}
