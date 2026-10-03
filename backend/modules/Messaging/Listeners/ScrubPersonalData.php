<?php

namespace Modules\Messaging\Listeners;

use App\Support\Contracts\ErasesPersonalData;
use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\Messaging\Models\EventLog;

/**
 * When someone's data is erased, remove every logged event that was sent to their phone number
 * or mentions it. Safe to run twice.
 */
class ScrubPersonalData implements ShouldQueue
{
    public function handle(ErasesPersonalData $event): void
    {
        $phone = $event->phone();

        // The columns are JSON, so they are read as text for the search. This works on Postgres and SQLite.
        EventLog::query()
            ->whereRaw('CAST(recipients AS TEXT) LIKE ?', ["%{$phone}%"])
            ->orWhereRaw('CAST(payload AS TEXT) LIKE ?', ["%{$phone}%"])
            ->delete();
    }
}
