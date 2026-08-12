<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Minimal shape for dropdowns/comboboxes — id + name only, nothing an
 * admin management screen needs (code, is_active). See DepartmentResource
 * for the full shape.
 *
 * @mixin \App\Models\Department
 */
class DepartmentOptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
        ];
    }
}
