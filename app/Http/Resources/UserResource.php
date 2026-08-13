<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\User */
class UserResource extends JsonResource
{
    /**
     * Set when the caller already knows this resource represents the
     * authenticated actor (e.g. AuthController::login(), where the request
     * carries no bearer token yet, so $request->user() is unavailable).
     */
    private bool $isSelf = false;

    public function asSelf(): static
    {
        $this->isSelf = true;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isSelf = $this->isSelf || ($request->user() && $this->id === $request->user()->id);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'employee_code' => $this->employee_code,
            'mobile' => $this->mobile,
            'department_id' => $this->department_id,
            'manager_id' => $this->manager_id,
            'department' => $this->whenLoaded('department', fn () => $this->department ? [
                'id' => $this->department->id,
                'name' => $this->department->name,
            ] : null),
            'manager' => $this->whenLoaded('manager', fn () => $this->manager ? [
                'id' => $this->manager->id,
                'name' => $this->manager->name,
            ] : null),
            'roles' => $this->getRoleNames(),
            'is_active' => $this->is_active,
            'relieved_on' => $this->relieved_on,
            // Direct reports only — how many other users currently have this
            // one as manager_id. Only present when eager-loaded via
            // withCount('reports') (the admin list, and after status/relieve
            // mutations) — null on /me and /login, which never load it.
            'reports_count' => $this->reports_count,
            'created_at' => $this->created_at,

            // Only meaningful — and only included — when this resource represents
            // the authenticated user themself, not an entry in a users list/dropdown.
            // Every authenticated user can create a work item now (at minimum via
            // self-assignment), so this is unconditionally true rather than a
            // policy check — TaskAssignmentAuthorizer is only ever consulted
            // against a specific target user, never a bare "can create?" ability.
            'abilities' => $this->when(
                $isSelf,
                fn () => [
                    'can_create_work_items' => true,
                    // Whether this plain `user` has at least one direct report — drives
                    // whether the Task Register nav item/route is worth showing them at
                    // all, versus just the self-assign button on My Tasks.
                    'is_reporting_manager' => $this->reports()->exists(),
                ],
            ),
        ];
    }
}
