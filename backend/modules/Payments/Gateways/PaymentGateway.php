<?php

namespace Modules\Payments\Gateways;

use Modules\Payments\Models\Payment;

/** One way of taking mobile money. To add a provider, implement this and name it in GatewayManager. */
interface PaymentGateway
{
    public function key(): string;

    /** Ask the gateway to prompt the payer. The result arrives later, by callback or status check. */
    public function charge(Payment $payment): ChargeResult;

    /** Ask the gateway what happened to a payment, for when the callback never came. */
    public function status(Payment $payment): StatusResult;
}
