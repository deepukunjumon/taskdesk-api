<?php

namespace App\Repositories\Eloquent;

use App\Enums\Role;
use App\Enums\WorkItemStatus;
use App\Models\User;
use App\Models\WorkItem;
use App\Repositories\Contracts\WorkItemRepositoryInterface;
use App\Services\HierarchyService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class EloquentWorkItemRepository implements WorkItemRepositoryInterface
{
    private const EAGER = ['department', 'branch', 'category', 'assignedTo', 'assignedBy', 'createdBy'];

    private const SORTABLE = ['created_at', 'priority', 'status', 'task_id'];

    public function __construct(
        private readonly HierarchyService $hierarchy,
    ) {}

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

        // A plain user sees: items assigned to them, items they themself
        // assigned to someone else (e.g. a manager delegating to a report),
        // and items assigned to anyone in their reporting chain — including
        // a report's own self-assigned task, which has no assigned_by tie
        // to the manager at all.
        $descendantIds = $this->hierarchy->getDescendants($actor)->pluck('id');

        $query->where(function (Builder $q) use ($actor, $descendantIds) {
            $q->where('assigned_to_id', $actor->id)
                ->orWhere('assigned_by_id', $actor->id)
                ->when(
                    $descendantIds->isNotEmpty(),
                    fn (Builder $q2) => $q2->orWhereIn('assigned_to_id', $descendantIds),
                );
        });
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        $query
            ->when(! empty($filters['status']), fn (Builder $q) => $q->whereIn('status', $filters['status']))
            ->when(! empty($filters['priority']), fn (Builder $q) => $q->whereIn('priority', $filters['priority']))
            ->when(! empty($filters['department_id']), fn (Builder $q) => $q->whereIn('department_id', $filters['department_id']))
            ->when(! empty($filters['assigned_to_id']), fn (Builder $q) => $q->whereIn('assigned_to_id', $filters['assigned_to_id']))
            ->when(! empty($filters['entry_type']), fn (Builder $q) => $q->whereIn('entry_type', $filters['entry_type']))
            ->when($filters['branch_id'] ?? null, fn (Builder $q, $v) => $q->where('branch_id', $v))
            ->when($filters['category_id'] ?? null, fn (Builder $q, $v) => $q->where('category_id', $v))
            ->when($filters['date_from'] ?? null, fn (Builder $q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($filters['date_to'] ?? null, fn (Builder $q, $v) => $q->whereDate('created_at', '<=', $v));
    }
}
