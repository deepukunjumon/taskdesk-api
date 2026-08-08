<?php

namespace App\Repositories\Contracts;

use App\Models\User;

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
}
