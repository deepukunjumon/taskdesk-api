<?php

namespace App\Http\Controllers\Api;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBranchRequest;
use App\Http\Resources\BranchResource;
use App\Models\Branch;
use Illuminate\Http\JsonResponse;

/**
 * Simple lookup CRUD — see DepartmentController for rationale.
 */
class BranchController extends Controller
{
    public function index(): JsonResponse
    {
        return BranchResource::collection(Branch::orderBy('name')->get())->response();
    }

    public function store(StoreBranchRequest $request): JsonResponse
    {
        abort_unless($request->user()->hasRole([Role::SuperAdmin->value, Role::Admin->value]), 403);

        $branch = Branch::create($request->validated());

        return (new BranchResource($branch))->response()->setStatusCode(201);
    }
}
