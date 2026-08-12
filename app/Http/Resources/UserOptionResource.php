<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Minimal shape for the "Assigned To" dropdown/combobox — id, name, and
 * department_id (needed to derive which departments an actor's reports
 * span — see WorkItemForm on the frontend). Deliberately excludes email,
 * manager_id, roles, and abilities: roles in particular calls
 * getRoleNames(), a DB round trip per user that a dropdown never needs.
 * See UserResource for the full shape used by /users and /me.
 *
 * @mixin \App\Models\User
 */
class UserOptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'department_id' => $this->department_id,
        ];
    }
}
