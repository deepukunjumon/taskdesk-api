<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class UserService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly HierarchyService $hierarchy,
    ) {}

    public function currentUser(): ?User
    {
        return Auth::user();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, User>
     */
    public function all(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->users->all();
    }

    public function findById(string $id): ?User
    {
        return $this->users->findById($id);
    }

    /**
     * The actor's own record plus everyone assignable to them — every
     * descendant for a plain user, or every user for admin/superadmin.
     * Backs the "Assign To" dropdown so the frontend never has to know the
     * hierarchy rules itself. An optional $departmentId narrows the result
     * to that department, matching the department-scoped check in
     * TaskAssignmentAuthorizer::canAssign() so the dropdown never offers a
     * choice the backend would then reject.
     *
     * @return Collection<int, User>
     */
    public function assignableFor(User $actor, ?string $departmentId = null): Collection
    {
        $candidates = $actor->hasRole([Role::SuperAdmin->value, Role::Admin->value])
            ? collect($this->users->all()->all())
            : $this->hierarchy->getDescendants($actor)->prepend($actor);

        if ($departmentId === null) {
            return $candidates;
        }

        return $candidates->filter(fn (User $user) => $user->department_id === $departmentId)->values();
    }

    /**
     * @throws ValidationException
     */
    public function updateManager(User $target, ?string $managerId): User
    {
        if ($this->hierarchy->wouldCreateCycle($target, $managerId)) {
            throw ValidationException::withMessages([
                'manager_id' => ['This change would create a circular reporting structure.'],
            ]);
        }

        $target->manager_id = $managerId;
        $target->save();

        return $target;
    }

    /**
     * @throws ValidationException
     */
    public function login(string $email, string $password): array
    {
        $user = $this->users->findByEmail($email);

        if (! $user || ! password_verify($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return [$user, $token];
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }
}
