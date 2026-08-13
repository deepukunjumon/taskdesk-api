<?php

namespace App\Repositories\Contracts;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface UserRepositoryInterface
{
    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, User>
     */
    public function all(): \Illuminate\Database\Eloquent\Collection;

    public function findById(string $id): ?User;

    public function findByEmail(string $email): ?User;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): User;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator;
}
