<?php

namespace Modules\Support\Listeners;

use App\Support\Contracts\ErasesPersonalData;
use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\Support\Models\SupportCase;

/**
 * When someone's data is erased, her ordinary cases are deleted with everything said in them.
 * An incident is kept, because Aveo may have to account for it, but her name and number
 * are taken off it. Safe to run twice.
 */
class ScrubCases implements ShouldQueue
{
    public function handle(ErasesPersonalData $event): void
    {
        SupportCase::query()->where('phone', $event->phone())->where('is_incident', false)->delete();

        SupportCase::query()->where('phone', $event->phone())->update(['name' => null, 'phone' => null, 'account_id' => null]);
    }
}
