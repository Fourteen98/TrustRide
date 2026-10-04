<?php

namespace Modules\Payments\Events;

use App\Support\BaseDomainEvent;
use App\Support\Money;
use Modules\Payments\Models\Payment;

/**
 * Shared by the payment events. Payments doesn't know who paid or what for, only "a thing with a type and an id".
 * The module that asked for the payment listens, checks payableType is its own, and acts.
 * There are no recipients: the owning module tells the person, in its own words.
 */
abstract class PaymentEvent extends BaseDomainEvent
{
    public int $paymentId;

    public string $payableType;

    public int $payableId;

    /** @var array<string, mixed> */
    private array $data;

    public function __construct(Payment $payment)
    {
        $this->paymentId = $payment->id;
        $this->payableType = $payment->payable_type;
        $this->payableId = $payment->payable_id;
        $this->data = [
            'payment_id' => $payment->id,
            'reference' => $payment->reference,
            'payable_type' => $payment->payable_type,
            'payable_id' => $payment->payable_id,
            'amount' => Money::format($payment->amount),
            'network' => $payment->network,
            'provider' => $payment->provider,
            'reason' => $payment->failure_reason,
        ];
    }

    public function payload(): array
    {
        return $this->data;
    }
}
