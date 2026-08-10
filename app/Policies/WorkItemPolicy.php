<?php

namespace App\Policies;

use App\Enums\Role;
use App\Enums\WorkItemStatus;
use App\Models\User;
use App\Models\WorkItem;

/**
 * Item-level authorization only. List-level scoping lives in
 * EloquentWorkItemRepository::paginate() — these two are the only places
 * department/role scoping logic is allowed to live.
 *
 * editableFields() is the single source of truth for which fields a user may
 * change via the general update endpoint — UpdateWorkItemRequest filters its
 * validation rules against it, and WorkItemResource exposes the same list to
 * the frontend, so no field-access rule is ever duplicated.
 */
class WorkItemPolicy
{
    /**
     * @var list<string>
     */
    private const FULL_EDIT_FIELDS = [
        'entry_type',
        'assigned_by',
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
    private const EMPLOYEE_EDIT_FIELDS = ['resolution', 'remarks'];

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, WorkItem $item): bool
    {
        return $this->isInScope($user, $item);
    }

    public function create(User $user): bool
    {
        return $user->hasRole([Role::SuperAdmin->value, Role::Admin->value]);
    }

    public function update(User $user, WorkItem $item): bool
    {
        return $this->isEditable($item) && $this->isInScope($user, $item);
    }

    public function updateStatus(User $user, WorkItem $item): bool
    {
        return $this->isEditable($item) && $this->isInScope($user, $item);
    }

    public function reassign(User $user, WorkItem $item): bool
    {
        return $this->isEditable($item) && $this->canManage($user, $item);
    }

    public function delete(User $user, WorkItem $item): bool
    {
        return $this->isDeletable($item) && $this->canManage($user, $item);
    }

    /**
     * @return list<string>
     */
    public function editableFields(User $user, WorkItem $item): array
    {
        if (! $this->update($user, $item)) {
            return [];
        }

        if ($user->hasRole(Role::Employee->value)) {
            return self::EMPLOYEE_EDIT_FIELDS;
        }

        return self::FULL_EDIT_FIELDS;
    }

    private function isEditable(WorkItem $item): bool
    {
        return ($item->status !== WorkItemStatus::Deleted && $item->status !== WorkItemStatus::Closed);
    }

    private function isDeletable(WorkItem $item): bool
    {
        return ($item->status !== WorkItemStatus::Deleted && $item->status !== WorkItemStatus::Closed);
    }

    /** Superadmin or an admin within the item's own department — never an employee. */
    private function canManage(User $user, WorkItem $item): bool
    {
        if ($user->hasRole(Role::SuperAdmin->value)) {
            return true;
        }

        if ($user->hasRole(Role::Admin->value)) {
            return $item->department_id === $user->department_id;
        }

        return false;
    }

    private function isInScope(User $user, WorkItem $item): bool
    {
        if ($user->hasRole(Role::SuperAdmin->value)) {
            return true;
        }

        if ($user->hasRole(Role::Admin->value)) {
            return $item->department_id === $user->department_id;
        }

        return $item->assigned_to_id === $user->id;
    }
}
