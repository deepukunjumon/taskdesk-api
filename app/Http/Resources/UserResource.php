<?php

namespace App\Http\Resources;

use App\Models\WorkItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\User */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'department_id' => $this->department_id,
            'roles' => $this->getRoleNames(),
            'created_at' => $this->created_at,

            // Only meaningful — and only included — when this resource represents
            // the authenticated user themself, not an entry in a users list/dropdown.
            'abilities' => $this->when(
                $request->user() && $this->id === $request->user()->id,
                fn () => [
                    'can_create_work_items' => $request->user()->can('create', WorkItem::class),
                ],
            ),
        ];
    }
}
