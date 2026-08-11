<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDepartmentRequest extends FormRequest
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
            // A soft-deleted department's code is free to reuse.
            'code' => ['required', 'string', 'max:50', Rule::unique('departments', 'code')->whereNull('deleted_at')],
        ];
    }
}
