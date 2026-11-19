<?php

namespace App\Http\Requests;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClosureRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user?->hasRole(Role::LabManager, Role::DepartmentHead)) {
            return false;
        }
        $closure = $this->route('lab_closure');

        return ! $closure || $user->managesLab($closure->lab_id)
            || $user->headsDepartment($closure->lab()->value('department_id'));
    }

    public function rules(): array
    {
        $user = $this->user();
        $closure = $this->route('lab_closure');
        $started = $closure && $closure->starts_at->lte(now());

        return [
            'lab_id' => ['required', 'integer', Rule::exists('labs', 'id')->where(fn ($query) => $user->hasRole(Role::LabManager)
                ? $query->where('id', $user->lab_id)
                : $query->where('department_id', $user->department_id)),
                ...($started ? [Rule::in([$closure->lab_id])] : [])],
            'starts_at' => ['required', 'date', $started
                ? 'date_equals:'.$closure->starts_at->toDateTimeString()
                : 'after_or_equal:now'],
            'ends_at' => ['required', 'date', 'after:starts_at', 'after_or_equal:now'],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
