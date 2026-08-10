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

    public function canAssign(User $actor, User $target): bool
    {
        if ($actor->hasRole([Role::SuperAdmin->value, Role::Admin->value])) {
            return true;
        }

        if ($actor->id === $target->id) {
            return true;
        }

        return $this->hierarchy->isAncestorOf($actor, $target);
    }
}
