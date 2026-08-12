<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            // An empty/omitted array means "common" — applies to every department.
            'department_ids' => ['sometimes', 'array'],
            'department_ids.*' => ['uuid', 'exists:departments,id'],
        ];
    }
}
