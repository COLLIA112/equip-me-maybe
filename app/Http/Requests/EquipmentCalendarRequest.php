<?php

namespace App\Http\Requests;

class EquipmentCalendarRequest extends NormalizedFilterRequest
{
    public function rules(): array
    {
        return ['week' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31']];
    }
}
