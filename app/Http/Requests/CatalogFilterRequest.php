<?php

namespace App\Http\Requests;

use App\Enums\EquipmentStatus;
use Illuminate\Validation\Rule;

class CatalogFilterRequest extends NormalizedFilterRequest
{
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:150'],
            'model' => ['nullable', 'string', 'max:255'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'lab_id' => ['nullable', 'integer', 'exists:labs,id'],
            'status' => ['nullable', Rule::enum(EquipmentStatus::class)],
            'tag' => ['nullable', 'integer', 'exists:tags,id'],
            'sort' => ['nullable', Rule::in(['name', 'serial_number', 'status', 'created_at'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ] + $this->paginationRules();
    }
}
