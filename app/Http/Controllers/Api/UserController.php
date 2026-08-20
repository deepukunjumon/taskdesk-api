<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexUserRequest;
use App\Http\Requests\RelieveUserRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserManagerRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Requests\UpdateUserStatusRequest;
use App\Http\Resources\UserOptionResource;
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

    public function index(IndexUserRequest $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $filters = $request->validated();
        if (array_key_exists('is_active', $filters)) {
            $filters['is_active'] = $request->boolean('is_active');
        }

        $perPage = (int) ($filters['per_page'] ?? 15);

        return UserResource::collection($this->users->list($filters, $perPage))->response();
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $created = $this->users->create($request->validated());

        return (new UserResource($created))->response()->setStatusCode(201);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $this->authorize('update', User::class);

        $updated = $this->users->update($user, $request->validated());

        return (new UserResource($updated))->response();
    }

    public function updateStatus(UpdateUserStatusRequest $request, User $user): JsonResponse
    {
        $this->authorize('updateStatus', User::class);

        $updated = $this->users->updateStatus($user, $request->boolean('is_active'));

        return (new UserResource($updated))->response();
    }

    public function relieve(RelieveUserRequest $request, User $user): JsonResponse
    {
        $this->authorize('relieve', User::class);

        $updated = $this->users->relieve($user, $request->validated('relieved_on'));

        return (new UserResource($updated))->response();
    }

    /**
     * Backs the "Assign To" dropdown/combobox — the actor's own record plus
     * everyone they're allowed to assign a task to, so the frontend never
     * re-derives hierarchy rules itself. An optional `department_id`
     * narrows the list to that department, for when the caller has already
     * picked which department the task is being filed under. An optional
     * `q` narrows it further to a name search. Returns the minimal
     * UserOptionResource shape — this endpoint only ever backs a dropdown,
     * never a page that needs email/roles/manager_id.
     */
    public function assignable(Request $request): JsonResponse
    {
        $users = $this->users->assignableFor(
            $request->user(),
            $request->query('department_id'),
            $request->query('q'),
        );

        return UserOptionResource::collection($users)->response();
    }

    public function updateManager(UpdateUserManagerRequest $request, User $user): JsonResponse
    {
        $this->authorize('updateManager', User::class);

        $updated = $this->users->updateManager($user, $request->validated('manager_id'));

        return (new UserResource($updated))->response();
    }

    /**
     * The authenticated user's own direct reports — lets a reporting
     * manager see their team without needing admin/superadmin access to
     * the full Users list. Self-scoped by construction; no policy gate.
     */
    public function myReports(Request $request): JsonResponse
    {
        return UserResource::collection($this->users->myReports($request->user()))->response();
    }
}
