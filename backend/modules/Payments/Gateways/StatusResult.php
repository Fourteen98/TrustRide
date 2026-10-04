<?php

namespace Modules\Payments\Gateways;

/**
 * What a gateway said when asked what happened to a payment.
 * "known" is false when the gateway couldn't answer. That means "still unknown", never "failed".
 */
final readonly class StatusResult
{
    private function __construct(
        public bool $known,
        public bool $paid = false,
        public ?string $transactionId = null,
        public mixed $raw = null,
    ) {}

    public static function unknown(mixed $raw = null): self
    {
        return new self(false, raw: $raw);
    }

    public static function paid(?string $transactionId, mixed $raw = null): self
    {
        return new self(true, true, $transactionId, $raw);
    }

    public static function unpaid(mixed $raw = null): self
    {
        return new self(true, false, raw: $raw);
    }
}
