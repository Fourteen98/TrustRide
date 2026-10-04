<?php

namespace Modules\Subscriptions\Actions;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DomainException;
use Modules\Access\Models\Staff;
use Modules\Subscriptions\Events\RunAnnouncement;
use Modules\Subscriptions\Models\Run;
use Modules\Subscriptions\Queries\TodaysRiders;

/**
 * Sends one message to every rider expected on a run on a given day.
 * Riders who said they won't ride that day are left out.
 */
class AnnounceToRun
{
    public function __construct(private TodaysRiders $riders) {}

    /** @return int How many riders it went to */
    public function handle(Run $run, string $message, ?Staff $by = null, ?CarbonInterface $date = null): int
    {
        $message = trim($message);
        $date = CarbonImmutable::parse($date ?? now())->startOfDay();

        if ($message === '') {
            throw new DomainException('Write the message first.');
        }

        if (mb_strlen($message) > 300) {
            throw new DomainException('Keep it under 300 characters so it fits in two text messages.');
        }

        $riders = $this->riders->handle($run, $date)->where('absent', false)->values();

        if ($riders->isEmpty()) {
            throw new DomainException("Nobody is riding on this run on {$date->format('l j F')}.");
        }

        RunAnnouncement::dispatch($run->loadMissing(['route', 'driver']), $message, $date->toDateString(), $riders, $by?->id);

        return $riders->count();
    }
}
