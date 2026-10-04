<?php

namespace Modules\Subscriptions\Events;

use App\Support\BaseDomainEvent;
use App\Support\Recipient;
use Illuminate\Support\Collection;
use Modules\Subscriptions\Models\Run;

/**
 * An operator has something to tell every rider on a run, such as "the van is 15 minutes late".
 * Subscriptions knows who is riding. Messaging turns this into messages through its rule for run.announcement.
 */
class RunAnnouncement extends BaseDomainEvent
{
    /** @var array<string, mixed> */
    private array $data;

    /** @var list<Recipient> */
    private array $riders;

    /** @param  Collection<int, array{name: string, phone: string, channel: ?string}>  $riders */
    public function __construct(Run $run, string $message, string $date, Collection $riders, ?int $staffId)
    {
        $this->data = [
            'message' => $message,
            'run_id' => $run->id,
            'route' => $run->route->name,
            'time' => $run->departureTime(),
            'driver_name' => $run->driver->name,
            'date' => $date,
            'sent_by' => $staffId,
        ];

        $this->riders = $riders
            ->map(fn (array $rider) => new Recipient(role: 'rider', phone: $rider['phone'], channel: $rider['channel'], name: $rider['name']))
            ->values()
            ->all();
    }

    public function name(): string
    {
        return 'run.announcement';
    }

    public function payload(): array
    {
        return $this->data;
    }

    public function recipients(): array
    {
        return $this->riders;
    }
}
