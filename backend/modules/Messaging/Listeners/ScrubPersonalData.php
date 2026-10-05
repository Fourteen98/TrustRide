<?php

namespace Modules\Messaging\Listeners;

use App\Support\Contracts\ErasesPersonalData;
use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\Messaging\Models\EventLog;
use Modules\Messaging\Models\Message;
use Modules\Messaging\Models\PushDevice;

/**
 * When someone's data is erased, remove every message sent to their phone number and every
 * logged event that was addressed to it or mentions it. Safe to run twice.
 */
class ScrubPersonalData implements ShouldQueue
{
    public function handle(ErasesPersonalData $event): void
    {
        $phone = $event->phone();

        // Messages go first. Those tied to an event also go when the event is deleted below.
        Message::query()->where('recipient_phone', $phone)->delete();
        PushDevice::query()->where('phone', $phone)->delete();

        // The columns are JSON, so they are read as text for the search. This works on Postgres and SQLite.
        EventLog::query()
            ->whereRaw('CAST(recipients AS TEXT) LIKE ?', ["%{$phone}%"])
            ->orWhereRaw('CAST(payload AS TEXT) LIKE ?', ["%{$phone}%"])
            ->delete();
    }
}
