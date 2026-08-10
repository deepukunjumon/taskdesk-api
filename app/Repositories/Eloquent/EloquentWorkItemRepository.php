<?php

namespace App\Repositories\Eloquent;

use App\Enums\Role;
use App\Enums\WorkItemStatus;
use App\Models\User;
use App\Models\WorkItem;
use App\Repositories\Contracts\WorkItemRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class EloquentWorkItemRepository implements WorkItemRepositoryInterface
{
    private const EAGER = ['department', 'branch', 'category', 'assignedTo', 'assignedBy', 'createdBy'];

    private const SORTABLE = ['created_at', 'priority', 'status', 'work_id'];

    public function findById(string $id): ?WorkItem
    {
        return WorkItem::with(self::EAGER)->find($id);
    }

    public function create(array $attributes): WorkItem
    {
        return WorkItem::create($attributes);
    }

    public function update(WorkItem $item, array $attributes): WorkItem
    {
        $item->update($attributes);

        return $item;
    }

    public function paginate(User $actor, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = WorkItem::query()
            ->with(self::EAGER)
            ->where('status', '!=', WorkItemStatus::Deleted->value);

        $this->scopeToActor($query, $actor);
        $this->applyFilters($query, $filters);

        $sortBy = in_array($filters['sort_by'] ?? null, self::SORTABLE, true) ? $filters['sort_by'] : 'created_at';
        $sortDir = ($filters['sort_dir'] ?? null) === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sortBy, $sortDir)->paginate($perPage);
    }

    public function nextWorkNumber(): int
    {
        return DB::transaction(function () {
            $row = DB::table('work_item_sequences')->where('id', 1)->lockForUpdate()->first();
            $next = $row->next_number;

            DB::table('work_item_sequences')->where('id', 1)->update(['next_number' => $next + 1]);

            return $next;
        });
    }

    public function countsByStatus(User $actor): array
    {
        $base = WorkItem::query()->where('status', '!=', WorkItemStatus::Deleted->value);
        $this->scopeToActor($base, $actor);

        $byStatus = (clone $base)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $overdue = (clone $base)
            ->where('status', '!=', WorkItemStatus::Closed->value)
            ->whereNotNull('sla_due_at')
            ->where('sla_due_at', '<', now())
            ->count();

        return [
            'total' => (int) $byStatus->sum(),
            'open' => (int) ($byStatus[WorkItemStatus::Open->value] ?? 0),
            'in_progress' => (int) ($byStatus[WorkItemStatus::InProgress->value] ?? 0),
            'pending' => (int) ($byStatus[WorkItemStatus::Pending->value] ?? 0),
            'closed' => (int) ($byStatus[WorkItemStatus::Closed->value] ?? 0),
            'overdue' => $overdue,
        ];
    }

    private function scopeToActor(Builder $query, User $actor): void
    {
        if ($actor->hasRole([Role::SuperAdmin->value, Role::Admin->value])) {
            return;
        }

        $query->where('assigned_to_id', $actor->id);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        $query
            ->when($filters['status'] ?? null, fn (Builder $q, $v) => $q->where('status', $v))
            ->when($filters['priority'] ?? null, fn (Builder $q, $v) => $q->where('priority', $v))
            ->when($filters['department_id'] ?? null, fn (Builder $q, $v) => $q->where('department_id', $v))
            ->when($filters['assigned_to_id'] ?? null, fn (Builder $q, $v) => $q->where('assigned_to_id', $v))
            ->when($filters['entry_type'] ?? null, fn (Builder $q, $v) => $q->where('entry_type', $v))
            ->when($filters['branch_id'] ?? null, fn (Builder $q, $v) => $q->where('branch_id', $v))
            ->when($filters['category_id'] ?? null, fn (Builder $q, $v) => $q->where('category_id', $v))
            ->when($filters['date_from'] ?? null, fn (Builder $q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($filters['date_to'] ?? null, fn (Builder $q, $v) => $q->whereDate('created_at', '<=', $v));
    }
}
