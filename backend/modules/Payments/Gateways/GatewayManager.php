<?php

namespace Modules\Payments\Gateways;

use InvalidArgumentException;

/** Hands out the gateway named in config('payments.driver'), or the one a past payment was made with. */
class GatewayManager
{
    /** @var array<string, PaymentGateway> */
    private array $overrides = [];

    public function gateway(?string $key = null): PaymentGateway
    {
        $key ??= config('payments.driver');

        return $this->overrides[$key] ?? match ($key) {
            'fake' => app(FakeGateway::class),
            'hubtel' => app(HubtelGateway::class),
            default => throw new InvalidArgumentException("Unknown payments driver [{$key}]."),
        };
    }

    /** Swap a gateway. Tests use this. */
    public function use(string $key, PaymentGateway $gateway): void
    {
        $this->overrides[$key] = $gateway;
    }
}
