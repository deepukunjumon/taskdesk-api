<?php

namespace App\Http\Controllers\Api;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDepartmentRequest;
use App\Http\Resources\DepartmentResource;
use App\Models\Department;
use Illuminate\Http\JsonResponse;

/**
 * Simple lookup CRUD (per Phase 2 spec: "basic CRUD, low priority within
 * this phase") — intentionally not routed through the Repository/Service
 * pattern since there is no business logic beyond a straight create/list.
 */
class DepartmentController extends Controller
{
    public function index(): JsonResponse
    {
        return DepartmentResource::collection(Department::orderBy('name')->get())->response();
    }

    public function store(StoreDepartmentRequest $request): JsonResponse
    {
        abort_unless($request->user()->hasRole([Role::SuperAdmin->value, Role::Admin->value]), 403);

        $department = Department::create($request->validated());

        return (new DepartmentResource($department))->response()->setStatusCode(201);
    }
}
