<?php

namespace App\Services;

use App\Enums\WorkItemStatus;
use App\Models\WorkItem;
use Illuminate\Validation\ValidationException;

/**
 * Enforces the work item status state machine. Adding a new status only
 * requires extending ALLOWED_TRANSITIONS — no other class needs to change.
 */
class WorkItemStatusTransitioner
{
    /**
     * @var array<string, list<string>>
     */
    private const ALLOWED_TRANSITIONS = [
        WorkItemStatus::Open->value => [
            WorkItemStatus::InProgress->value,
            WorkItemStatus::Closed->value,
            WorkItemStatus::Deleted->value,
        ],
        WorkItemStatus::InProgress->value => [
            WorkItemStatus::Pending->value,
            WorkItemStatus::Closed->value,
            WorkItemStatus::Deleted->value,
        ],
        WorkItemStatus::Pending->value => [
            WorkItemStatus::InProgress->value,
            WorkItemStatus::Closed->value,
            WorkItemStatus::Deleted->value,
        ],
        WorkItemStatus::Closed->value => [WorkItemStatus::Deleted->value],
        WorkItemStatus::Deleted->value => [],
    ];

    /**
     * The statuses selectable from the generic status dropdown for a given
     * current status. Excludes "deleted" — that's reached only through the
     * dedicated delete action/endpoint, never this control — even though
     * ALLOWED_TRANSITIONS permits it internally for WorkItemService::delete().
     *
     * @return list<string>
     */
    public static function nextStatuses(WorkItemStatus $from): array
    {
        return array_values(array_diff(
            self::ALLOWED_TRANSITIONS[$from->value] ?? [],
            [WorkItemStatus::Deleted->value],
        ));
    }

    /**
     * Mutates the given WorkItem in place (status + derived timestamps).
     * Does not persist — the caller is responsible for saving inside a transaction.
     *
     * @throws ValidationException
     */
    public function transition(WorkItem $item, WorkItemStatus $to): void
    {
        $from = $item->status;

        if (! in_array($to->value, self::ALLOWED_TRANSITIONS[$from->value] ?? [], true)) {
            throw ValidationException::withMessages([
                'status' => ["Cannot transition from \"{$from->value}\" to \"{$to->value}\"."],
            ]);
        }

        if ($to === WorkItemStatus::Closed && blank($item->resolution)) {
            throw ValidationException::withMessages([
                'resolution' => ['Resolution is required to close a work item.'],
            ]);
        }

        // Covers both the normal open -> in_progress path and a direct
        // open -> closed transition (e.g. work already done, logged late) —
        // either way start_time gets a value instead of staying null.
        $entersActiveWork = in_array($to, [WorkItemStatus::InProgress, WorkItemStatus::Closed], true);
        if ($entersActiveWork && $item->start_time === null) {
            $item->start_time = now();
        }

        if ($to === WorkItemStatus::Closed) {
            $item->end_time = now();
        }

        $item->status = $to;
    }
}
