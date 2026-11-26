<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RevokeReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('revoke', $this->route('reservation'));
    }

    public function rules(): array
    {
        return ['cancellation_reason' => ['required', 'string', 'min:5', 'max:1000']];
    }
}