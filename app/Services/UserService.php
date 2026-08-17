<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->users->paginate($filters, $perPage);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): User
    {
        $role = $attributes['role'];
        unset($attributes['role']);

        // `password` is cast `hashed` on the model, so the plain value here
        // is hashed automatically on save — no manual Hash::make() needed.
        $target = $this->users->create($attributes);
        $target->assignRole($role);

        return $target->fresh(['department', 'manager'])->loadCount('reports');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(User $target, array $attributes): User
    {
        $target->update($attributes);

        return $target->fresh(['department', 'manager'])->loadCount('reports');
    }

    public function updateStatus(User $target, bool $isActive): User
    {
        $target->is_active = $isActive;
        $target->save();

        return $target->fresh(['department', 'manager'])->loadCount('reports');
    }

    public function relieve(User $target, string $relievedOn): User
    {
        $target->relieved_on = $relievedOn;
        $target->is_active = false;
        $target->save();

        return $target->fresh(['department', 'manager'])->loadCount('reports');
    }

    /**
     * The actor's own record plus everyone assignable to them — every
     * descendant for a plain user, or every user for admin/superadmin.
     * Backs the "Assign To" dropdown so the frontend never has to know the
     * hierarchy rules itself. An optional $departmentId narrows the result
     * to that department, matching the department-scoped check in
     * TaskAssignmentAuthorizer::canAssign() so the dropdown never offers a
     * choice the backend would then reject. An optional $search narrows it
     * further to names containing the term, for a search-as-you-type
     * dropdown/combobox.
     *
     * @return Collection<int, User>
     */
    public function assignableFor(User $actor, ?string $departmentId = null, ?string $search = null): Collection
    {
        $candidates = $actor->hasRole([Role::SuperAdmin->value, Role::Admin->value])
            ? collect($this->users->all()->all())
            : $this->hierarchy->getDescendants($actor)->prepend($actor);

        // A relieved/disabled user is never a valid assignment target.
        $candidates = $candidates->filter(fn (User $user) => $user->is_active);

        if ($departmentId !== null) {
            $candidates = $candidates->filter(fn (User $user) => $user->department_id === $departmentId);
        }

        if ($search !== null && $search !== '') {
            $candidates = $candidates->filter(
                fn (User $user) => str_contains(strtolower($user->name), strtolower($search)),
            );
        }

        return $candidates->values();
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

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['This account has been disabled. Contact an administrator.'],
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
