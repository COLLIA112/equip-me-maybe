<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RejectReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage', $this->route('reservation'));
    }

    public function rules(): array
    {
        return ['rejection_reason' => ['nullable', 'string', 'max:1000']];
    }
}
