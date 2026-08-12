<?php

namespace App\Http\Controllers\Api;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDepartmentRequest;
use App\Http\Requests\UpdateDepartmentRequest;
use App\Http\Resources\DepartmentOptionResource;
use App\Http\Resources\DepartmentResource;
use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Simple lookup CRUD (per Phase 2 spec: "basic CRUD, low priority within
 * this phase") — intentionally not routed through the Repository/Service
 * pattern since there is no business logic beyond a straight create/list.
 *
 * Delete is always a soft delete (SoftDeletes on the model) — the row is
 * never removed, so existing work items/categories referencing it keep
 * working. `index()` defaults to active-only (what task-creation forms
 * want); pass `?include_inactive=1` for the admin management screen, which
 * needs to see disabled departments too so they can be re-enabled.
 */
class DepartmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $departments = Department::query()
            ->when(
                ! $request->boolean('include_inactive'),
                fn ($q) => $q->where('is_active', true),
            )
            ->orderBy('name')
            ->paginate($request->query('per_page', 15));

        return DepartmentResource::collection($departments)->response();
    }

    /**
     * Minimal, active-only lookup for dropdowns/comboboxes — id + name only,
     * optionally narrowed by `?q=` (name search). Deliberately separate from
     * index() so form/dropdown callers never pull code/is_active/pagination
     * metadata they don't need.
     */
    public function options(Request $request): JsonResponse
    {
        $departments = Department::query()
            ->where('is_active', true)
            ->when(
                $request->query('q'),
                fn ($q, $search) => $q->where('name', 'like', '%'.$search.'%'),
            )
            ->orderBy('name')
            ->limit(50)
            ->get(['id', 'name']);

        return DepartmentOptionResource::collection($departments)->response();
    }

    public function store(StoreDepartmentRequest $request): JsonResponse
    {
        abort_unless($request->user()->hasRole([Role::SuperAdmin->value, Role::Admin->value]), 403);

        $department = Department::create($request->validated());

        return (new DepartmentResource($department))->response()->setStatusCode(201);
    }

    public function update(UpdateDepartmentRequest $request, Department $department): JsonResponse
    {
        abort_unless($request->user()->hasRole([Role::SuperAdmin->value, Role::Admin->value]), 403);

        $department->update($request->validated());

        return (new DepartmentResource($department))->response();
    }

    public function toggleActive(Request $request, Department $department): JsonResponse
    {
        abort_unless($request->user()->hasRole([Role::SuperAdmin->value, Role::Admin->value]), 403);

        $department->update(['is_active' => ! $department->is_active]);

        return (new DepartmentResource($department))->response();
    }

    public function destroy(Request $request, Department $department): JsonResponse
    {
        abort_unless($request->user()->hasRole([Role::SuperAdmin->value, Role::Admin->value]), 403);

        $department->delete();

        return response()->json([
            'success' => true,
            'message' => 'Department deleted.',
        ]);
    }
}
