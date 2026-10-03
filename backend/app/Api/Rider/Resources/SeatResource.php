<?php

namespace App\Api\Rider\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Subscriptions\Models\Seat;

/**
 * Her place on a run. This is the receipt: car number, colour, driver, stop and time.
 *
 * @mixin Seat
 */
class SeatResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            /** reserved (approved, not yet paid) or active */
            'status' => $this->status->value,
            /** outbound (to the destination) or return */
            'direction' => $this->run->direction->value,
            'route' => $this->run->route->name,
            'stop' => $this->stop->name,
            'landmark' => $this->stop->landmark,
            /** When the van reaches her stop, e.g. "06:38" */
            'time' => $this->pickupTime(),
            /**
             * ISO weekdays the van runs, 1 for Monday to 7 for Sunday
             *
             * @var list<int>
             */
            'days' => array_map('intval', $this->run->days_of_week ?? []),
            'driver_name' => $this->run->driver->name,
            'car_number' => $this->run->vehicle->registration_number,
            'car_colour' => $this->run->vehicle->colour,
            'car_make' => $this->run->vehicle->make,
        ];
    }
}
