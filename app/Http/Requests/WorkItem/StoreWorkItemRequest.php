<?php

namespace App\Http\Requests\WorkItem;

use App\Enums\EntryType;
use App\Enums\Priority;
use App\Enums\Source;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkItemRequest extends FormRequest
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
            'department_id' => ['required', 'uuid', 'exists:departments,id'],
            'entry_type' => ['required', Rule::enum(EntryType::class)],
            'assigned_to_id' => ['required', 'uuid', 'exists:users,id'],
            'source' => ['required', Rule::enum(Source::class)],
            'branch_id' => ['nullable', 'uuid', 'exists:branches,id'],
            'category_id' => ['nullable', 'uuid', 'exists:categories,id'],
            'priority' => ['required', Rule::enum(Priority::class)],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
        ];
    }
}
