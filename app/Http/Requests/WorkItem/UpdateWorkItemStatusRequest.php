<?php

namespace App\Http\Requests\WorkItem;

use App\Enums\WorkItemStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkItemStatusRequest extends FormRequest
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
            'status' => ['required', Rule::enum(WorkItemStatus::class)],
            'resolution' => ['required_if:status,closed', 'nullable', 'string'],
            'note' => ['nullable', 'string'],
        ];
    }
}
