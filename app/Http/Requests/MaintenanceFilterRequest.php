<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class MaintenanceFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // EquipmentPolicy is enforced by the controller.
    }

    public function rules(): array
    {
        return [];
    }

    /** Invalid query values are ignored rather than breaking the list page. */
    public function filters(): array
    {
        $rules = [
            'status' => ['nullable', Rule::in(['open', 'resolved'])],
            'sort' => ['nullable', Rule::in(['created_at', 'issue', 'resolved_at'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ];
        $filters = [];
        foreach ($rules as $key => $rule) {
            $value = $this->query($key);
            if (is_string($value) && $value !== '' && Validator::make([$key => $value], [$key => $rule])->passes()) {
                $filters[$key] = $value;
            }
        }

        return $filters;
    }
}
