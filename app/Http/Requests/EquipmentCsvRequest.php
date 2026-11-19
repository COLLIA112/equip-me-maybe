<?php

namespace App\Http\Requests;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;

class EquipmentCsvRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(Role::LabManager) && $this->user()->lab_id !== null;
    }

    public function rules(): array
    {
        return ['file' => ['required', 'file', 'max:2048', 'mimetypes:text/plain,text/csv,application/csv,application/vnd.ms-excel']];
    }
}
