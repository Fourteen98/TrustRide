<?php

namespace App\Api\Rider\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Riders\Models\RiderApplication;

/** @mixin RiderApplication */
class ApplicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            /** pending, approved, rejected or withdrawn */
            'status' => $this->status->value,
            'route' => ['id' => $this->route_id, 'name' => $this->route->name],
            'stop' => ['id' => $this->stop_id, 'name' => $this->stop->name],
            /** "06:30", or null if she didn't ask for a morning seat */
            'morning_time' => $this->morning_time ? substr((string) $this->morning_time, 0, 5) : null,
            'evening_time' => $this->evening_time ? substr((string) $this->evening_time, 0, 5) : null,
            /** A sentence to show her, when rejected */
            'rejection_reason' => $this->rejection_reason?->label(),
            'rejection_note' => $this->rejection_note,
            'submitted_at' => $this->created_at->toIso8601String(),
        ];
    }
}
