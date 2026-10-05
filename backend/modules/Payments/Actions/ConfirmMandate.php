<?php

namespace Modules\Payments\Actions;

use DomainException;
use Modules\Payments\Gateways\GatewayManager;
use Modules\Payments\Models\PaymentMandate;

/** Hands the gateway the code she was texted. The approval itself still arrives as an event. */
class ConfirmMandate
{
    public function __construct(private GatewayManager $gateways) {}

    public function handle(PaymentMandate $mandate, string $code): void
    {
        if (! $mandate->isPending()) {
            throw new DomainException('There is nothing waiting for a code.');
        }

        $result = $this->gateways->gateway($mandate->provider)->confirmMandate($mandate, trim($code));
        $mandate->remember('confirm', $result->raw);

        if (! $result->accepted) {
            throw new DomainException($result->error ?: 'That code was not accepted.');
        }
    }
}
