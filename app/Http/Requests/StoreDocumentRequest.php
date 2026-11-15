<?php

namespace App\Http\Requests;

use App\Models\Document;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // record level check is DocumentPolicy@create inside the controller
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::in(Document::TYPES)],
            'is_required' => ['nullable', 'boolean'],
            'file' => ['required', 'file', 'mimes:pdf', 'max:5120'],
        ];
    }
}
