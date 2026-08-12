<?php

namespace App\Http\Controllers\Api;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBranchRequest;
use App\Http\Requests\UpdateBranchRequest;
use App\Http\Resources\BranchResource;
use App\Models\Branch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Simple lookup CRUD — see DepartmentController for rationale, including the
 * soft-delete and `?include_inactive=1` conventions.
 */
class BranchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $branches = Branch::query()
            ->when(
                ! $request->boolean('include_inactive'),
                fn ($q) => $q->where('is_active', true),
            )
            ->orderBy('name')
            ->paginate($request->query('per_page', 15));

        return BranchResource::collection($branches)->response();
    }

    public function store(StoreBranchRequest $request): JsonResponse
    {
        abort_unless($request->user()->hasRole([Role::SuperAdmin->value, Role::Admin->value]), 403);

        $branch = Branch::create($request->validated());

        return (new BranchResource($branch))->response()->setStatusCode(201);
    }

    public function update(UpdateBranchRequest $request, Branch $branch): JsonResponse
    {
        abort_unless($request->user()->hasRole([Role::SuperAdmin->value, Role::Admin->value]), 403);

        $branch->update($request->validated());

        return (new BranchResource($branch))->response();
    }

    public function toggleActive(Request $request, Branch $branch): JsonResponse
    {
        abort_unless($request->user()->hasRole([Role::SuperAdmin->value, Role::Admin->value]), 403);

        $branch->update(['is_active' => ! $branch->is_active]);

        return (new BranchResource($branch))->response();
    }

    public function destroy(Request $request, Branch $branch): JsonResponse
    {
        abort_unless($request->user()->hasRole([Role::SuperAdmin->value, Role::Admin->value]), 403);

        $branch->delete();

        return response()->json([
            'success' => true,
            'message' => 'Branch deleted.',
        ]);
    }
}
