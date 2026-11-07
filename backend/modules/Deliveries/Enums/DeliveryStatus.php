<?php

namespace Modules\Deliveries\Enums;

/** Where one parcel is. Pending until it is put on a run, then picked up at the business, then done one way or another. */
enum DeliveryStatus: string
{
    case Pending = 'pending';
    case Assigned = 'assigned';
    case PickedUp = 'picked_up';
    case Delivered = 'delivered';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Waiting for a run',
            self::Assigned => 'On a run',
            self::PickedUp => 'On its way',
            self::Delivered => 'Delivered',
            self::Failed => 'Couldn\'t deliver',
            self::Cancelled => 'Cancelled',
        };
    }

    /** Nothing more will happen to it. */
    public function isDone(): bool
    {
        return in_array($this, [self::Delivered, self::Failed, self::Cancelled], true);
    }
}
