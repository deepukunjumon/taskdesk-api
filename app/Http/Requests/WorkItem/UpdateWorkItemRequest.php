<?php

namespace App\Http\Requests\WorkItem;

use App\Enums\AssignedBy;
use App\Enums\EntryType;
use App\Enums\Priority;
use App\Enums\Source;
use App\Models\WorkItem;
use App\Policies\WorkItemPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Only validates fields WorkItemPolicy::editableFields() allows for this
     * user/item — the same list the frontend receives on the resource, so
     * "which fields can I edit" is never defined in more than one place.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var WorkItem $workItem */
        $workItem = $this->route('workItem');

        $allowedFields = app(WorkItemPolicy::class)->editableFields($this->user(), $workItem);

        $allRules = [
            'entry_type' => ['sometimes', Rule::enum(EntryType::class)],
            'assigned_by' => ['sometimes', Rule::enum(AssignedBy::class)],
            'source' => ['sometimes', Rule::enum(Source::class)],
            'branch_id' => ['sometimes', 'nullable', 'uuid', 'exists:branches,id'],
            'category_id' => ['sometimes', 'nullable', 'uuid', 'exists:categories,id'],
            'priority' => ['sometimes', Rule::enum(Priority::class)],
            'subject' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string'],
            'resolution' => ['sometimes', 'nullable', 'string'],
            'remarks' => ['sometimes', 'nullable', 'string'],
        ];

        return array_intersect_key($allRules, array_flip($allowedFields));
    }
}
