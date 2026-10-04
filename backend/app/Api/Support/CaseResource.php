<?php

namespace App\Api\Support;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Support\Enums\EntryType;
use Modules\Support\Models\CaseEntry;
use Modules\Support\Models\SupportCase;

/** @mixin SupportCase */
class CaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            /** @var 'open'|'resolved' */
            'status' => $this->status->value,
            /** What it is about, as she chose it. e.g. "The van didn't come" */
            'about' => $this->category->label(),
            'opened_at' => $this->created_at->toIso8601String(),
            /** True once a member of staff has picked it up */
            'taken' => $this->assigned_to !== null,
            /** What was said, oldest first. Staff notes are not included. */
            'messages' => $this->entries
                ->filter(fn (CaseEntry $entry) => $entry->type->isVisibleToPerson())
                ->map(fn (CaseEntry $entry) => [
                    'from' => $entry->type === EntryType::Message ? 'you' : 'trustride',
                    'body' => (string) $entry->body,
                    'at' => $entry->created_at->toIso8601String(),
                ])
                ->values()
                ->all(),
        ];
    }
}
