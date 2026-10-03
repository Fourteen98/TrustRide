<?php

namespace App\Api\Rider\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Subscriptions\Models\AbsenceNotice;

/** @mixin AbsenceNotice */
class NoticeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            /** "2026-10-07" */
            'date' => $this->date->toDateString(),
            'in_time' => $this->in_time,
            /** True if her subscription was made a day longer */
            'credited' => $this->credited,
        ];
    }
}
