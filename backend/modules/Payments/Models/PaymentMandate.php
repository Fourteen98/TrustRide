<?php

namespace Modules\Payments\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Payments\Enums\MandateStatus;

class PaymentMandate extends Model
{
    protected $fillable = [
        'reference', 'owner_type', 'owner_id', 'phone', 'network', 'provider', 'status', 'provider_mandate_id',
        'verification', 'otp_prefix', 'failure_reason', 'approved_at', 'cancelled_at', 'meta',
    ];

    protected $attributes = ['status' => 'pending'];

    protected function casts(): array
    {
        return [
            'status' => MandateStatus::class,
            'approved_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function isPending(): bool
    {
        return $this->status === MandateStatus::Pending;
    }

    public function isActive(): bool
    {
        return $this->status === MandateStatus::Active;
    }

    /** Adds to meta without losing what is already there. */
    public function remember(string $key, mixed $value): void
    {
        $this->update(['meta' => [...($this->meta ?? []), $key => $value]]);
    }
}
