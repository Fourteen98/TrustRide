<?php

namespace Modules\Subscriptions\Events;

use App\Support\BaseDomainEvent;
use App\Support\Money;
use App\Support\Recipient;
use Modules\Subscriptions\Models\AutoRenewal;

abstract class AutoRenewalEvent extends BaseDomainEvent
{
    public int $autoRenewalId;

    /** @var array<string, mixed> */
    private array $data;

    private Recipient $rider;

    /** @param array<string, mixed> $extra */
    public function __construct(AutoRenewal $renewal, array $extra = [])
    {
        $renewal->loadMissing(['rider.account', 'plan', 'mandate']);

        $this->autoRenewalId = $renewal->id;
        $this->data = [
            'auto_renewal_id' => $renewal->id,
            'rider_id' => $renewal->rider_id,
            'rider_name' => $renewal->rider->name,
            'plan' => $renewal->plan->name,
            'amount' => Money::format($renewal->plan->price),
            'network' => config('payments.networks.'.$renewal->mandate?->network.'.label', 'mobile money'),
            // The last four digits only. Enough for her to know which wallet.
            'wallet' => $renewal->mandate?->phone ? substr($renewal->mandate->phone, -4) : '',
            'reason' => $renewal->last_error,
            ...$extra,
        ];
        $this->rider = new Recipient('rider', $renewal->rider->account->phone, $renewal->rider->account->preferred_channel, $renewal->rider->name);
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
