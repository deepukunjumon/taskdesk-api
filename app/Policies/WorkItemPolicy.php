<?php

namespace App\Policies;

use App\Enums\Role;
use App\Enums\WorkItemStatus;
use App\Models\User;
use App\Models\WorkItem;
use App\Services\HierarchyService;
use App\Services\TaskAssignmentAuthorizer;

/**
 * Item-level authorization only. List-level scoping lives in
 * EloquentWorkItemRepository::paginate() — these two are the only places
 * role scoping logic is allowed to live.
 *
 * editableFields() is the single source of truth for which fields a user may
 * change via the general update endpoint — UpdateWorkItemRequest filters its
 * validation rules against it, and WorkItemResource exposes the same list to
 * the frontend, so no field-access rule is ever duplicated.
 *
 * Assignment (create/reassign) authorization is never decided here directly —
 * it always delegates to TaskAssignmentAuthorizer, the single source of truth
 * for "can actor A assign a task to user B".
 */
class WorkItemPolicy
{
    /**
     * @var list<string>
     */
    private const FULL_EDIT_FIELDS = [
        'entry_type',
        'source',
        'branch_id',
        'category_id',
        'priority',
        'subject',
        'description',
        'resolution',
        'remarks',
    ];

    /**
     * @var list<string>
     */
    private const USER_EDIT_FIELDS = ['resolution', 'remarks'];

    public function __construct(
        private readonly TaskAssignmentAuthorizer $assignmentAuthorizer,
        private readonly HierarchyService $hierarchy,
    ) {}

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, WorkItem $item): bool
    {
        return $this->isInScope($user, $item);
    }

    public function create(User $user, User $target, ?string $departmentId = null): bool
    {
        return $this->assignmentAuthorizer->canAssign($user, $target, $departmentId);
    }

    public function update(User $user, WorkItem $item): bool
    {
        return $this->isEditable($item) && $this->isInScope($user, $item);
    }

    public function updateStatus(User $user, WorkItem $item): bool
    {
        return $this->isEditable($item) && $this->isInScope($user, $item);
    }

    public function reassign(User $user, WorkItem $item, User $newAssignee): bool
    {
        return $this->isEditable($item)
            && $this->assignmentAuthorizer->canAssign($user, $newAssignee, $item->department_id);
    }

    /**
     * Generic "could this user reassign this item to *someone*" check, used
     * only to decide whether the frontend shows a Reassign control at all —
     * the actual gate for a specific target is reassign() above.
     */
    public function canReassign(User $user, WorkItem $item): bool
    {
        if (! $this->isEditable($item)) {
            return false;
        }

        if ($this->canManage($user)) {
            return true;
        }

        return $this->hierarchy->getDescendants($user)->isNotEmpty();
    }

    public function delete(User $user, WorkItem $item): bool
    {
        return $this->isDeletable($item) && $this->canManage($user);
    }

    /**
     * @return list<string>
     */
    public function editableFields(User $user, WorkItem $item): array
    {
        if (! $this->isEditable($item) || ! $this->isInScope($user, $item)) {
            return [];
        }

        if ($this->canManage($user)) {
            return self::FULL_EDIT_FIELDS;
        }

        return self::USER_EDIT_FIELDS;
    }

    private function isEditable(WorkItem $item): bool
    {
        return ($item->status !== WorkItemStatus::Deleted && $item->status !== WorkItemStatus::Closed);
    }

    private function isDeletable(WorkItem $item): bool
    {
        return ($item->status !== WorkItemStatus::Deleted && $item->status !== WorkItemStatus::Closed);
    }

    /** Global — superadmin or admin, never department-conditional. */
    private function canManage(User $user): bool
    {
        return $user->hasRole([Role::SuperAdmin->value, Role::Admin->value]);
    }

    private function isInScope(User $user, WorkItem $item): bool
    {
        if ($this->canManage($user)) {
            return true;
        }

        if ($item->assigned_to_id === $user->id || $item->assigned_by_id === $user->id) {
            return true;
        }

        // Covers a report's own self-assigned task, which has no assigned_by
        // tie to the manager at all — matches the list-level scoping in
        // EloquentWorkItemRepository::scopeToActor().
        return $this->hierarchy->getDescendants($user)->contains('id', $item->assigned_to_id);
    }
}
