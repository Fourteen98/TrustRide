<?php

namespace Modules\Subscriptions\Enums;

enum PurchaseStatus: string
{
    case Pending = 'pending'; // the rider has been prompted and hasn't answered yet
    case Activated = 'activated'; // paid, and the subscription has started
    case Failed = 'failed'; // not paid
    case NeedsAttention = 'needs_attention'; // paid, but the subscription could not be started

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Waiting for payment',
            self::Activated => 'Paid and active',
            self::Failed => 'Not paid',
            self::NeedsAttention => 'Paid, needs attention',
        };
    }
}
