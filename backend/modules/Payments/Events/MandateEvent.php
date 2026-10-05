<?php

namespace Modules\Payments\Events;

use App\Support\BaseDomainEvent;
use Modules\Payments\Models\PaymentMandate;

/** Shared by the mandate events. Like payments, a mandate belongs to "a type and an id", and its owner listens. */
abstract class MandateEvent extends BaseDomainEvent
{
    public int $mandateId;

    public string $ownerType;

    public int $ownerId;

    /** @var array<string, mixed> */
    private array $data;

    public function __construct(PaymentMandate $mandate)
    {
        $this->mandateId = $mandate->id;
        $this->ownerType = $mandate->owner_type;
        $this->ownerId = $mandate->owner_id;
        $this->data = [
            'mandate_id' => $mandate->id,
            'reference' => $mandate->reference,
            'owner_type' => $mandate->owner_type,
            'owner_id' => $mandate->owner_id,
            'network' => $mandate->network,
            'provider' => $mandate->provider,
            'reason' => $mandate->failure_reason,
        ];
    }

    public function payload(): array
    {
        return $this->data;
    }
}
