<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Single source of truth for reporting-chain (manager_id) traversal. No other
 * class should write raw recursive queries against manager_id — controllers
 * and policies depend on this service instead.
 */
class HierarchyService
{
    /**
     * Every manager above $user, any depth, ordered nearest-first.
     *
     * @return Collection<int, User>
     */
    public function getAncestors(User $user): Collection
    {
        $rows = DB::select(<<<'SQL'
            WITH RECURSIVE ancestors AS (
                SELECT id, manager_id, 1 AS depth
                FROM users
                WHERE id = (SELECT manager_id FROM users WHERE id = ?)

                UNION ALL

                SELECT u.id, u.manager_id, a.depth + 1
                FROM users u
                INNER JOIN ancestors a ON u.id = a.manager_id
            )
            SELECT id, depth FROM ancestors ORDER BY depth ASC
        SQL, [$user->id]);

        return $this->hydrateOrdered($rows);
    }

    /**
     * Every user below $user in the chain, any depth.
     *
     * @return Collection<int, User>
     */
    public function getDescendants(User $user): Collection
    {
        $rows = DB::select(<<<'SQL'
            WITH RECURSIVE descendants AS (
                SELECT id, manager_id, 1 AS depth
                FROM users
                WHERE manager_id = ?

                UNION ALL

                SELECT u.id, u.manager_id, d.depth + 1
                FROM users u
                INNER JOIN descendants d ON u.manager_id = d.id
            )
            SELECT id, depth FROM descendants ORDER BY depth ASC
        SQL, [$user->id]);

        return $this->hydrateOrdered($rows);
    }

    public function isAncestorOf(User $potentialManager, User $target): bool
    {
        return $this->getAncestors($target)->contains('id', $potentialManager->id);
    }

    /**
     * True if setting $user's manager to $newManagerId would create a loop —
     * i.e. the prospective manager is $user themself or already a descendant
     * of $user. Must be checked on every manager_id change, not just creation.
     */
    public function wouldCreateCycle(User $user, ?string $newManagerId): bool
    {
        if ($newManagerId === null) {
            return false;
        }

        if ($newManagerId === $user->id) {
            return true;
        }

        return $this->getDescendants($user)->contains('id', $newManagerId);
    }

    /**
     * @param  array<int, object{id: string, depth: int}>  $rows
     * @return Collection<int, User>
     */
    private function hydrateOrdered(array $rows): Collection
    {
        $ids = collect($rows)->pluck('id');

        if ($ids->isEmpty()) {
            return collect();
        }

        $users = User::whereIn('id', $ids)->get()->keyBy('id');

        return $ids->map(fn (string $id) => $users->get($id))->filter()->values();
    }
}
