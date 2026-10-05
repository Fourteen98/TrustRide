<?php

namespace Modules\Payments\Gateways;

/** What a gateway said when asked to set up, or confirm, a mandate. Accepted means "she has been asked", not "approved". */
final readonly class MandateResult
{
    private function __construct(
        public bool $accepted,
        public ?string $providerId = null,
        public ?string $verification = null,
        public ?string $otpPrefix = null,
        public ?string $error = null,
        public mixed $raw = null,
    ) {}

    public static function accepted(?string $providerId, ?string $verification = null, ?string $otpPrefix = null, mixed $raw = null): self
    {
        return new self(true, $providerId, $verification, $otpPrefix, null, $raw);
    }

    public static function refused(string $error, mixed $raw = null): self
    {
        return new self(false, error: $error, raw: $raw);
    }
}
