<?php

namespace App\Http\Requests\WorkItem;

use App\Enums\EntryType;
use App\Enums\Priority;
use App\Enums\WorkItemStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexWorkItemRequest extends FormRequest
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
            'status' => ['sometimes', Rule::enum(WorkItemStatus::class)],
            'priority' => ['sometimes', Rule::enum(Priority::class)],
            'department_id' => ['sometimes', 'uuid'],
            'assigned_to_id' => ['sometimes', 'uuid'],
            'entry_type' => ['sometimes', Rule::enum(EntryType::class)],
            'branch_id' => ['sometimes', 'uuid'],
            'category_id' => ['sometimes', 'uuid'],
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date'],
            'sort_by' => ['sometimes', 'in:created_at,priority,status,task_id'],
            'sort_dir' => ['sometimes', 'in:asc,desc'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
