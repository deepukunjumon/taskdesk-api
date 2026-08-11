<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\User;

/**
 * The only place that decides "can actor A assign a task to user B" —
 * WorkItemPolicy and WorkItemService delegate here for every create/assign/
 * reassign check rather than duplicating the rule inline.
 */
class TaskAssignmentAuthorizer
{
    public function __construct(
        private readonly HierarchyService $hierarchy,
    ) {}

    /**
     * $departmentId, when given, is the department the task is being filed
     * under — for a non-admin actor the target must actually belong to it,
     * so a manager can't assign into a department they don't have a report
     * in just because the target happens to be somewhere in their hierarchy.
     */
    public function canAssign(User $actor, User $target, ?string $departmentId = null): bool
    {
        if ($actor->hasRole([Role::SuperAdmin->value, Role::Admin->value])) {
            return true;
        }

        if ($actor->id === $target->id) {
            return true;
        }

        if (! $this->hierarchy->isAncestorOf($actor, $target)) {
            return false;
        }

        return $departmentId === null || $target->department_id === $departmentId;
    }
}
