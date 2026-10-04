<?php

namespace Modules\Payments\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Payments\Enums\PaymentStatus;

class Payment extends Model
{
    protected $fillable = [
        'reference', 'payable_type', 'payable_id', 'description', 'amount', 'currency', 'phone', 'network',
        'provider', 'status', 'provider_transaction_id', 'failure_reason', 'paid_at', 'meta',
    ];

    protected $attributes = ['status' => 'initiated', 'currency' => 'GHS'];

    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'amount' => 'integer',
            'paid_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function isWaiting(): bool
    {
        return $this->status === PaymentStatus::Initiated;
    }

    /** Adds to meta without losing what is already there. */
    public function remember(string $key, mixed $value): void
    {
        $this->update(['meta' => [...($this->meta ?? []), $key => $value]]);
    }
}
