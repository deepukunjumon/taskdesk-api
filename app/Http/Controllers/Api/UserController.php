<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateUserManagerRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(
        private readonly UserService $users,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        return UserResource::collection($this->users->all())->response();
    }

    /**
     * Backs the "Assign To" dropdown — the actor's own record plus everyone
     * they're allowed to assign a task to, so the frontend never re-derives
     * hierarchy rules itself.
     */
    public function assignable(Request $request): JsonResponse
    {
        return UserResource::collection($this->users->assignableFor($request->user()))->response();
    }

    public function updateManager(UpdateUserManagerRequest $request, User $user): JsonResponse
    {
        $this->authorize('updateManager', User::class);

        $updated = $this->users->updateManager($user, $request->validated('manager_id'));

        return (new UserResource($updated))->response();
    }
}
