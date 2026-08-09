<?php

namespace App\Http\Controllers\Api;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Simple lookup CRUD — see DepartmentController for rationale.
 */
class CategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $categories = Category::query()
            ->when($request->query('department_id'), fn ($q, $id) => $q->where('department_id', $id))
            ->orderBy('name')
            ->get();

        return CategoryResource::collection($categories)->response();
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        abort_unless($request->user()->hasRole([Role::SuperAdmin->value, Role::Admin->value]), 403);

        $category = Category::create($request->validated());

        return (new CategoryResource($category))->response()->setStatusCode(201);
    }
}
