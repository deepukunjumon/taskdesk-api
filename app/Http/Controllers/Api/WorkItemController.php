<?php

namespace App\Http\Controllers\Api;

use App\Enums\WorkItemStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\WorkItem\IndexWorkItemRequest;
use App\Http\Requests\WorkItem\ReassignWorkItemRequest;
use App\Http\Requests\WorkItem\StoreWorkItemRequest;
use App\Http\Requests\WorkItem\UpdateWorkItemRequest;
use App\Http\Requests\WorkItem\UpdateWorkItemStatusRequest;
use App\Http\Resources\WorkItemResource;
use App\Models\WorkItem;
use App\Services\WorkItemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkItemController extends Controller
{
    public function __construct(
        private readonly WorkItemService $workItems,
    ) {}

    public function index(IndexWorkItemRequest $request): JsonResponse
    {
        $this->authorize('viewAny', WorkItem::class);

        $perPage = (int) ($request->validated('per_page') ?? 15);
        $items = $this->workItems->list($request->user(), $request->validated(), $perPage);

        return WorkItemResource::collection($items)->response();
    }

    public function store(StoreWorkItemRequest $request): JsonResponse
    {
        $this->authorize('create', WorkItem::class);

        $item = $this->workItems->create($request->validated(), $request->user());

        return (new WorkItemResource($item))->response()->setStatusCode(201);
    }

    public function show(WorkItem $workItem): JsonResponse
    {
        $this->authorize('view', $workItem);

        $workItem->load([
            'department',
            'branch',
            'category',
            'assignedTo',
            'createdBy',
            'timelines.actor',
        ]);

        return (new WorkItemResource($workItem))->response();
    }

    public function update(UpdateWorkItemRequest $request, WorkItem $workItem): JsonResponse
    {
        $this->authorize('update', $workItem);

        $updated = $this->workItems->update($workItem, $request->validated(), $request->user());

        return (new WorkItemResource($updated))->response();
    }

    public function updateStatus(UpdateWorkItemStatusRequest $request, WorkItem $workItem): JsonResponse
    {
        $this->authorize('updateStatus', $workItem);

        $updated = $this->workItems->updateStatus(
            $workItem,
            WorkItemStatus::from($request->validated('status')),
            $request->validated('resolution'),
            $request->validated('note'),
            $request->user(),
        );

        return (new WorkItemResource($updated))->response();
    }

    public function reassign(ReassignWorkItemRequest $request, WorkItem $workItem): JsonResponse
    {
        $this->authorize('reassign', $workItem);

        $updated = $this->workItems->reassign(
            $workItem,
            $request->validated('assigned_to_id'),
            $request->validated('note'),
            $request->user(),
        );

        return (new WorkItemResource($updated))->response();
    }

    public function destroy(Request $request, WorkItem $workItem): JsonResponse
    {
        $this->authorize('delete', $workItem);

        $this->workItems->delete($workItem, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Work item deleted.',
        ]);
    }
}
