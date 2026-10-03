<?php

namespace Modules\Subscriptions\Actions;

use Modules\Subscriptions\Enums\SeatStatus;
use Modules\Subscriptions\Models\Seat;

class ReleaseSeat
{
    public function handle(Seat $seat): Seat
    {
        if ($seat->status !== SeatStatus::Released) {
            $seat->update(['status' => SeatStatus::Released, 'released_at' => now()]);
        }

        return $seat;
    }
}
