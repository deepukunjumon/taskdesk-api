<?php

namespace App\Http\Requests\WorkItem;

use App\Enums\EntryType;
use App\Enums\Priority;
use App\Enums\WorkItemStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexWorkItemRequest extends FormRequest
{
    /**
     * Fields that can have multiple values
     * @var list<string>
     */
    private const MULTI_VALUE_FIELDS = ['status', 'priority', 'entry_type', 'department_id', 'assigned_to_id'];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $split = [];

        foreach (self::MULTI_VALUE_FIELDS as $field) {
            $value = $this->query($field);

            if (is_string($value) && $value !== '') {
                $split[$field] = array_values(array_filter(explode(',', $value), fn ($v) => $v !== ''));
            }
        }

        $this->merge($split);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'array'],
            'status.*' => [Rule::enum(WorkItemStatus::class)],
            'priority' => ['sometimes', 'array'],
            'priority.*' => [Rule::enum(Priority::class)],
            'department_id' => ['sometimes', 'array'],
            'department_id.*' => ['uuid'],
            'assigned_to_id' => ['sometimes', 'array'],
            'assigned_to_id.*' => ['uuid'],
            'entry_type' => ['sometimes', 'array'],
            'entry_type.*' => [Rule::enum(EntryType::class)],
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
