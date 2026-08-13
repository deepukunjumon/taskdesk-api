<?php

namespace App\Services;

use App\Contracts\MailerInterface;
use App\Enums\WorkItemStatus;
use App\Models\SlaSetting;
use App\Models\User;
use App\Models\WorkItem;
use App\Repositories\Contracts\WorkItemRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class WorkItemService
{
    private const EAGER = ['department', 'branch', 'category', 'assignedTo', 'assignedBy', 'createdBy'];

    public function __construct(
        private readonly WorkItemRepositoryInterface $items,
        private readonly WorkItemStatusTransitioner $transitioner,
        private readonly MailerInterface $mailer,
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
        $item = DB::transaction(function () use ($attributes, $actor) {
            $number = $this->items->nextWorkNumber();

            $attributes['task_id'] = sprintf('T%04d', $number);
            $attributes['created_by_id'] = $actor->id;
            $attributes['assigned_by_id'] = $actor->id;
            $attributes['status'] = WorkItemStatus::Open->value;
            $attributes['sla_due_at'] = $this->computeSlaDueAt($attributes['priority']);

            $item = $this->items->create($attributes);

            $this->recordTimeline($item, $actor, 'created', null, WorkItemStatus::Open->value, null);

            return $this->find($item->id);
        });

        $this->notifyAssignmentIfNeeded($item, $actor);

        return $item;
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
        $updated = DB::transaction(function () use ($item, $to, $resolution, $note, $actor) {
            $from = $item->status;

            if ($resolution !== null) {
                $item->resolution = $resolution;
            }

            $this->transitioner->transition($item, $to);
            $item->save();

            $this->recordTimeline($item, $actor, 'status_changed', $from->value, $to->value, $note);

            return $this->find($item->id);
        });

        if ($to === WorkItemStatus::Closed) {
            $this->notifyCompletionIfNeeded($updated, $actor);
        }

        return $updated;
    }

    public function reassign(WorkItem $item, string $newAssigneeId, ?string $note, User $actor): WorkItem
    {
        $updated = DB::transaction(function () use ($item, $newAssigneeId, $note, $actor) {
            $previousAssigneeId = $item->assigned_to_id;
            $previousAssigneeName = $previousAssigneeId
                ? User::find($previousAssigneeId)?->name ?? $previousAssigneeId
                : 'Unassigned';
            $newAssigneeName = User::find($newAssigneeId)?->name ?? $newAssigneeId;

            $item->assigned_to_id = $newAssigneeId;
            $item->assigned_by_id = $actor->id;
            $item->save();

            $historyNote = trim(sprintf(
                'Reassigned from %s to %s.%s',
                $previousAssigneeName,
                $newAssigneeName,
                $note ? " {$note}" : '',
            ));

            $this->recordTimeline($item, $actor, 'reassigned', null, null, $historyNote, $newAssigneeName);

            return $this->find($item->id);
        });

        $this->notifyAssignmentIfNeeded($updated, $actor);

        return $updated;
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

    /**
     * Sends an email to the assignee if the actor is not the assignee.
     * 
     * @param WorkItem $item
     * @param User $actor
     * 
     * @return bool
     */
    private function notifyAssignmentIfNeeded(WorkItem $item, User $actor): bool
    {
        if ($item->assigned_to_id === $actor->id || ! $item->assignedTo) {
            return false;
        }

        $this->sendWorkItemEmail(
            $item,
            'emails.task-assigned',
            [
                'assignedTo' => $item->assignedTo->name,
                'assignedBy' => $actor
            ],
            "Task Assigned: {$item->task_id} — {$item->subject}",
        );

        return true;
    }

    /**
     * Sends an email to the assignee if the actor is not the assignee and the item is completed.
     * 
     * @param WorkItem $item
     * @param User $actor
     * 
     * @return bool
     */
    private function notifyCompletionIfNeeded(WorkItem $item, User $actor): bool
    {
        if ($item->assigned_to_id === $actor->id || ! $item->assignedTo) {
            return false;
        }

        $this->sendWorkItemEmail(
            $item,
            'emails.task-completed',
            ['completedBy' => $actor],
            "Task Completed: {$item->task_id} — {$item->subject}",
        );

        return true;
    }

    /**
     * Sends an email to the assignee with the given view and data.
     * 
     * @param  array<string, mixed>  $data
     */
    private function sendWorkItemEmail(WorkItem $item, string $view, array $data, string $subject): void
    {
        $taskUrl = rtrim(config('services.frontend.url'), '/').'/work-register';

        $htmlBody = view($view, [...$data, 'workItem' => $item, 'taskUrl' => $taskUrl])->render();

        $this->mailer->send($item->assignedTo->email, $subject, $htmlBody);
    }

    private function recordTimeline(
        WorkItem $item,
        User $actor,
        string $action,
        ?string $fromStatus,
        ?string $toStatus,
        ?string $note,
        ?string $assignedToName = null,
    ): void {
        $item->timelines()->create([
            'actor_id' => $actor->id,
            'action' => $action,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'assigned_to_name' => $assignedToName,
            'note' => $note,
        ]);
    }

}
