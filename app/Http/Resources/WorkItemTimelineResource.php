<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\WorkItemTimeline */
class WorkItemTimelineResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'actor' => $this->whenLoaded('actor', fn () => [
                'id' => $this->actor?->id,
                'name' => $this->actor?->name,
            ]),
            'action' => $this->action,
            'from_status' => $this->from_status,
            'to_status' => $this->to_status,
            'assigned_to_name' => $this->assigned_to_name,
            'note' => $this->note,
            'created_at' => $this->created_at,
        ];
    }
}
