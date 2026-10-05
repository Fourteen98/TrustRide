<?php

namespace Modules\Payments\Gateways;

use Modules\Payments\Models\Payment;
use Modules\Payments\Models\PaymentMandate;

/** One way of taking mobile money. To add a provider, implement this and name it in GatewayManager. */
interface PaymentGateway
{
    public function key(): string;

    /**
     * Ask the gateway for the money. With no mandate on the payment the payer is prompted.
     * With one, the money is taken without a prompt. Either way the result arrives later,
     * by callback or status check.
     */
    public function charge(Payment $payment): ChargeResult;

    /** Ask the gateway what happened to a payment, for when the callback never came. */
    public function status(Payment $payment): StatusResult;

    /** Ask the wallet's owner to approve a standing mandate. The approval arrives later, by callback. */
    public function requestMandate(PaymentMandate $mandate): MandateResult;

    /** Hand over the code she was sent, for networks that confirm a mandate that way. */
    public function confirmMandate(PaymentMandate $mandate, string $code): MandateResult;

    /** Withdraw a mandate at the gateway. True if the gateway agreed. */
    public function cancelMandate(PaymentMandate $mandate): bool;
}
