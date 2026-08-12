<?php

namespace App\Http\Controllers\Api;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Simple lookup CRUD — see DepartmentController for rationale, including the
 * soft-delete and `?include_inactive=1` conventions.
 */
class CategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $categories = Category::query()
            ->with('departments')
            // A category with no departments attached is a common one (e.g.
            // "General") that applies regardless of which department is
            // selected, so it must always be included alongside a specific match.
            ->when(
                $request->query('department_id'),
                fn ($q, $id) => $q->where(
                    fn ($q) => $q->whereHas('departments', fn ($q) => $q->where('departments.id', $id))
                        ->orWhereDoesntHave('departments'),
                ),
            )
            ->when(
                ! $request->boolean('include_inactive'),
                fn ($q) => $q->where('is_active', true),
            )
            ->orderBy('name')
            ->paginate($request->query('per_page', 15));

        return CategoryResource::collection($categories)->response();
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        abort_unless($request->user()->hasRole([Role::SuperAdmin->value, Role::Admin->value]), 403);

        $category = Category::create($request->safe()->only('name'));
        $category->departments()->sync($request->validated('department_ids', []));

        return (new CategoryResource($category->load('departments')))->response()->setStatusCode(201);
    }

    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        abort_unless($request->user()->hasRole([Role::SuperAdmin->value, Role::Admin->value]), 403);

        $category->update($request->safe()->only('name'));

        if ($request->has('department_ids')) {
            $category->departments()->sync($request->validated('department_ids'));
        }

        return (new CategoryResource($category->load('departments')))->response();
    }

    public function toggleActive(Request $request, Category $category): JsonResponse
    {
        abort_unless($request->user()->hasRole([Role::SuperAdmin->value, Role::Admin->value]), 403);

        $category->update(['is_active' => ! $category->is_active]);

        return (new CategoryResource($category))->response();
    }

    public function destroy(Request $request, Category $category): JsonResponse
    {
        abort_unless($request->user()->hasRole([Role::SuperAdmin->value, Role::Admin->value]), 403);

        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Category deleted.',
        ]);
    }
}
