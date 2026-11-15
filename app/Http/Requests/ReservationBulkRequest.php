<?php

namespace App\Http\Requests;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReservationBulkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole(Role::LabManager);
    }

    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['approve', 'reject'])],
            'reservation_ids' => ['required', 'array', 'min:1', 'max:50'],
            'reservation_ids.*' => ['required', 'integer', 'distinct', 'exists:reservations,id'],
            'rejection_reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
