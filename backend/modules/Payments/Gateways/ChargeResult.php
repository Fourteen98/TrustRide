<?php

namespace Modules\Payments\Gateways;

/** What a gateway said when asked to start a charge. Accepted means "the rider has been prompted", not "paid". */
final readonly class ChargeResult
{
    private function __construct(
        public bool $accepted,
        public ?string $transactionId = null,
        public ?string $error = null,
        public mixed $raw = null,
    ) {}

    public static function accepted(?string $transactionId, mixed $raw = null): self
    {
        return new self(true, $transactionId, null, $raw);
    }

    public static function refused(string $error, mixed $raw = null): self
    {
        return new self(false, null, $error, $raw);
    }
}
