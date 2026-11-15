<?php

namespace App\Http\Requests;

use App\Enums\ReservationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReservationFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Record and role authorization remains in the controller and policies.
    }

    protected function prepareForValidation(): void
    {
        $filters = [];
        $status = $this->query('status');
        if (is_string($status) && ReservationStatus::tryFrom($status)) {
            $filters['status'] = $status;
        }
        $search = $this->query('q');
        if (is_string($search) && mb_strlen(trim($search)) <= 150 && trim($search) !== '') {
            $filters['q'] = trim($search);
        }
        if (in_array($this->query('sort'), ['starts_at', 'ends_at', 'status', 'equipment', 'borrower'], true)) {
            $filters['sort'] = $this->query('sort');
        }
        if (in_array($this->query('direction'), ['asc', 'desc'], true)) {
            $filters['direction'] = $this->query('direction');
        }
        if (in_array($this->query('overdue'), ['1', 1, true], true)) {
            $filters['overdue'] = '1';
        }
        $page = $this->query('page');
        if (is_scalar($page) && filter_var($page, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false) {
            $filters['page'] = (int) $page;
        }
        // Pagination keeps only recognized, normalized query values.
        $this->query->replace($filters);
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', Rule::enum(ReservationStatus::class)],
            'q' => ['sometimes', 'string', 'max:150'],
            'sort' => ['sometimes', Rule::in(['starts_at', 'ends_at', 'status', 'equipment', 'borrower'])],
            'direction' => ['sometimes', Rule::in(['asc', 'desc'])],
            'overdue' => ['sometimes', 'boolean'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    public function filters(): array
    {
        return $this->safe()->only(['status', 'q', 'sort', 'direction', 'overdue']);
    }
}
