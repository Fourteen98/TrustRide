<?php

namespace Modules\Subscriptions\Events;

use App\Support\BaseDomainEvent;
use App\Support\Money;
use App\Support\Recipient;
use Modules\Subscriptions\Models\PlanPurchase;

abstract class PurchaseEvent extends BaseDomainEvent
{
    public int $purchaseId;

    /** @var array<string, mixed> */
    private array $data;

    private Recipient $rider;

    public function __construct(PlanPurchase $purchase)
    {
        $purchase->loadMissing(['rider.account', 'plan']);

        $this->purchaseId = $purchase->id;
        $this->data = [
            'purchase_id' => $purchase->id,
            'rider_id' => $purchase->rider_id,
            'rider_name' => $purchase->rider->name,
            'plan' => $purchase->plan->name,
            'amount' => Money::format($purchase->amount),
            'problem' => $purchase->problem,
        ];
        $this->rider = new Recipient(
            role: 'rider',
            phone: $purchase->rider->account->phone,
            channel: $purchase->rider->account->preferred_channel,
            name: $purchase->rider->name,
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
