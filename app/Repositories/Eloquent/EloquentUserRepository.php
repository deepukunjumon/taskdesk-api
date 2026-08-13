<?php

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class EloquentUserRepository implements UserRepositoryInterface
{
    public function all(): \Illuminate\Database\Eloquent\Collection
    {
        return User::all();
    }

    public function findById(string $id): ?User
    {
        return User::find($id);
    }

    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    public function create(array $attributes): User
    {
        return User::create($attributes);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = User::query()
            ->with(['department', 'manager'])
            ->withCount('reports')
            ->when($filters['role'] ?? null, fn (Builder $q, $role) => $q->role($role))
            ->when($filters['department_id'] ?? null, fn (Builder $q, $v) => $q->where('department_id', $v))
            ->when(
                array_key_exists('is_active', $filters) && $filters['is_active'] !== null,
                fn (Builder $q) => $q->where('is_active', $filters['is_active']),
            )
            ->when($filters['q'] ?? null, function (Builder $q, string $search) {
                $q->where(function (Builder $sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('employee_code', 'like', "%{$search}%");
                });
            });

        return $query->orderBy('name')->paginate($perPage);
    }
}
