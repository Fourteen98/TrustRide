<?php

namespace Modules\Payments\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Payments\Enums\PaymentStatus;

class Payment extends Model
{
    protected $fillable = [
        'reference', 'payable_type', 'payable_id', 'description', 'amount', 'currency', 'phone', 'network',
        'provider', 'status', 'mandate_id', 'provider_transaction_id', 'failure_reason', 'paid_at', 'meta',
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

    public function mandate(): BelongsTo
    {
        return $this->belongsTo(PaymentMandate::class, 'mandate_id');
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
