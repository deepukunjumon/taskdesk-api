<?php

namespace App\Http\Resources;

use App\Policies\WorkItemPolicy;
use App\Services\WorkItemStatusTransitioner;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\WorkItem */
class WorkItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $policy = app(WorkItemPolicy::class);

        return [
            'id' => $this->id,
            'work_id' => $this->work_id,
            'department' => new DepartmentResource($this->whenLoaded('department')),
            'entry_type' => $this->entry_type,
            'assigned_by' => $this->whenLoaded('assignedBy', fn () => $this->assignedBy ? [
                'id' => $this->assignedBy->id,
                'name' => $this->assignedBy->name,
            ] : null),
            'assigned_to' => $this->whenLoaded('assignedTo', fn () => [
                'id' => $this->assignedTo?->id,
                'name' => $this->assignedTo?->name,
            ]),
            'created_by' => $this->whenLoaded('createdBy', fn () => [
                'id' => $this->createdBy?->id,
                'name' => $this->createdBy?->name,
            ]),
            'source' => $this->source,
            'branch' => new BranchResource($this->whenLoaded('branch')),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'priority' => $this->priority,
            'subject' => $this->subject,
            'description' => $this->description,
            'status' => $this->status,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'resolution' => $this->resolution,
            'remarks' => $this->remarks,
            'sla_due_at' => $this->sla_due_at,
            'timeline' => WorkItemTimelineResource::collection($this->whenLoaded('timelines')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Drives the frontend entirely — it never re-derives these rules itself.
            'permissions' => $user ? [
                'can_update' => $policy->update($user, $this->resource),
                'can_update_status' => $policy->updateStatus($user, $this->resource),
                'can_reassign' => $policy->canReassign($user, $this->resource),
                'can_delete' => $policy->delete($user, $this->resource),
            ] : null,
            'editable_fields' => $user ? $policy->editableFields($user, $this->resource) : [],
            'next_statuses' => WorkItemStatusTransitioner::nextStatuses($this->status),
        ];
    }
}
