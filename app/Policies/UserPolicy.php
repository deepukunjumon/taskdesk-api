<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole([Role::SuperAdmin->value, Role::Admin->value]);
    }

    public function view(User $user, User $target): bool
    {
        return $user->hasRole([Role::SuperAdmin->value, Role::Admin->value]) || $user->id === $target->id;
    }

    public function updateManager(User $user): bool
    {
        return $user->hasRole([Role::SuperAdmin->value, Role::Admin->value]);
    }

    public function update(User $user): bool
    {
        return $user->hasRole([Role::SuperAdmin->value, Role::Admin->value]);
    }

    public function updateStatus(User $user): bool
    {
        return $user->hasRole([Role::SuperAdmin->value, Role::Admin->value]);
    }

    public function relieve(User $user): bool
    {
        return $user->hasRole([Role::SuperAdmin->value, Role::Admin->value]);
    }
}
