<?php

namespace App\Services;

use App\Enums\Role;
use App\Enums\WorkItemStatus;
use App\Models\SlaSetting;
use App\Models\User;
use App\Models\WorkItem;
use App\Repositories\Contracts\WorkItemRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkItemService
{
    private const EAGER = ['department', 'branch', 'category', 'assignedTo', 'createdBy'];

    public function __construct(
        private readonly WorkItemRepositoryInterface $items,
        private readonly WorkItemStatusTransitioner $transitioner,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(User $actor, array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->items->paginate($actor, $filters, $perPage);
    }

    public function find(string $id): ?WorkItem
    {
        return $this->items->findById($id);
    }

    /**
     * @return array{total: int, open: int, in_progress: int, pending: int, closed: int, overdue: int}
     */
    public function stats(User $actor): array
    {
        return $this->items->countsByStatus($actor);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes, User $actor): WorkItem
    {
        return DB::transaction(function () use ($attributes, $actor) {
            $this->guardDepartmentScope($actor, $attributes['department_id']);
            $this->guardAssigneeInDepartment($attributes['assigned_to_id'], $attributes['department_id']);

            $number = $this->items->nextWorkNumber();

            $attributes['work_id'] = sprintf('W%04d', $number);
            $attributes['created_by_id'] = $actor->id;
            $attributes['status'] = WorkItemStatus::Open->value;
            $attributes['sla_due_at'] = $this->computeSlaDueAt($attributes['priority']);

            $item = $this->items->create($attributes);

            $this->recordTimeline($item, $actor, 'created', null, WorkItemStatus::Open->value, null);

            return $this->find($item->id);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(WorkItem $item, array $attributes, User $actor): WorkItem
    {
        return DB::transaction(function () use ($item, $attributes, $actor) {
            $this->items->update($item, $attributes);

            $this->recordTimeline($item, $actor, 'updated', null, null, null);

            return $this->find($item->id);
        });
    }

    public function updateStatus(
        WorkItem $item,
        WorkItemStatus $to,
        ?string $resolution,
        ?string $note,
        User $actor,
    ): WorkItem {
        return DB::transaction(function () use ($item, $to, $resolution, $note, $actor) {
            $from = $item->status;

            if ($resolution !== null) {
                $item->resolution = $resolution;
            }

            $this->transitioner->transition($item, $to);
            $item->save();

            $this->recordTimeline($item, $actor, 'status_changed', $from->value, $to->value, $note);

            return $this->find($item->id);
        });
    }

    public function reassign(WorkItem $item, string $newAssigneeId, ?string $note, User $actor): WorkItem
    {
        return DB::transaction(function () use ($item, $newAssigneeId, $note, $actor) {
            $this->guardAssigneeInDepartment($newAssigneeId, $item->department_id);

            $previousAssigneeId = $item->assigned_to_id;
            $item->assigned_to_id = $newAssigneeId;
            $item->save();

            $historyNote = trim(sprintf(
                'Reassigned from %s to %s.%s',
                $previousAssigneeId,
                $newAssigneeId,
                $note ? " {$note}" : '',
            ));

            $this->recordTimeline($item, $actor, 'reassigned', null, null, $historyNote);

            return $this->find($item->id);
        });
    }

    /**
     * Logical delete only — sets status to "deleted" via the state machine.
     * The row is never removed from the database.
     */
    public function delete(WorkItem $item, User $actor): WorkItem
    {
        return DB::transaction(function () use ($item, $actor) {
            $from = $item->status;

            $this->transitioner->transition($item, WorkItemStatus::Deleted);
            $item->save();

            $this->recordTimeline($item, $actor, 'deleted', $from->value, WorkItemStatus::Deleted->value, null);

            return $this->find($item->id);
        });
    }

    private function computeSlaDueAt(string $priority): ?\Illuminate\Support\Carbon
    {
        $hours = SlaSetting::where('priority', $priority)->value('hours');

        return $hours ? now()->addHours($hours) : null;
    }

    private function recordTimeline(
        WorkItem $item,
        User $actor,
        string $action,
        ?string $fromStatus,
        ?string $toStatus,
        ?string $note,
    ): void {
        $item->timelines()->create([
            'actor_id' => $actor->id,
            'action' => $action,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'note' => $note,
        ]);
    }

    /**
     * Admins may only create/reassign within their own department.
     *
     * @throws ValidationException
     */
    private function guardDepartmentScope(User $actor, string $departmentId): void
    {
        if ($actor->hasRole(Role::SuperAdmin->value)) {
            return;
        }

        if ($departmentId !== $actor->department_id) {
            throw ValidationException::withMessages([
                'department_id' => ['You may only create work items within your own department.'],
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function guardAssigneeInDepartment(string $assigneeId, string $departmentId): void
    {
        $assignee = User::find($assigneeId);

        if (! $assignee || $assignee->department_id !== $departmentId) {
            throw ValidationException::withMessages([
                'assigned_to_id' => ['The assignee must belong to the work item\'s department.'],
            ]);
        }
    }
}
