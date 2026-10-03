<?php

namespace Modules\Riders\Events;

use App\Support\BaseDomainEvent;
use App\Support\Recipient;
use Modules\Riders\Models\RiderApplication;

/** Shared by the application events. Each one only says its name and adds to the payload. */
abstract class ApplicationEvent extends BaseDomainEvent
{
    public int $applicationId;

    /** @var array<string, mixed> */
    protected array $data;

    private Recipient $rider;

    public function __construct(RiderApplication $application)
    {
        $application->loadMissing(['rider.account', 'route', 'stop']);

        $this->applicationId = $application->id;
        $this->data = [
            'application_id' => $application->id,
            'rider_id' => $application->rider_id,
            'rider_name' => $application->rider->name,
            'route' => $application->route->name,
            'stop' => $application->stop->name,
        ];
        $this->rider = new Recipient(
            role: 'rider',
            phone: $application->rider->account->phone,
            channel: $application->rider->account->preferred_channel,
            name: $application->rider->name,
        );
    }

    public function payload(): array
    {
        return $this->data;
    }

    public function recipients(): array
    {
        return [$this->rider];
    }
}
