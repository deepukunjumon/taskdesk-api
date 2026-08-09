<?php

namespace App\Repositories\Contracts;

use App\Models\User;
use App\Models\WorkItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface WorkItemRepositoryInterface
{
    public function findById(string $id): ?WorkItem;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): WorkItem;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(WorkItem $item, array $attributes): WorkItem;

    /**
     * Returns a role/department-scoped, filtered, paginated list for the given actor.
     * This is the ONE place list-level scoping lives (alongside WorkItemPolicy for
     * single-item authorization) — no ad-hoc where() clauses in controllers/services.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginate(User $actor, array $filters, int $perPage): LengthAwarePaginator;

    /**
     * Atomically reserves and returns the next sequence number for work_id
     * generation (e.g. 1 -> "W0001"). Must be called inside a DB transaction.
     */
    public function nextWorkNumber(): int;

    /**
     * Status-bucketed counts (+ overdue) for the given actor's scope — the
     * same role/department scoping paginate() applies, never all-departments
     * data leaking into an admin's or employee's dashboard.
     *
     * @return array{total: int, open: int, in_progress: int, pending: int, closed: int, overdue: int}
     */
    public function countsByStatus(User $actor): array;
}
