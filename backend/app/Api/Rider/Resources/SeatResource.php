<?php

namespace App\Api\Rider\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Network\Models\Stop;
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
            'run_id' => $this->run_id,
            /** reserved (approved, not yet paid) or active */
            'status' => $this->status->value,
            /** outbound (to the destination) or return */
            'direction' => $this->run->direction->value,
            'route' => $this->run->route->name,
            /** Where this trip goes: the route's destination, or where it starts for a trip back. "Circle" */
            'heading_to' => $this->run->direction->value === 'outbound' ? $this->run->route->destination : $this->run->route->origin_area,
            /** Where she gets on: her own stop going out, her destination coming back */
            'stop' => $this->stopNamed($this->boardsAt())->name,
            'landmark' => $this->stopNamed($this->boardsAt())->landmark,
            /** When the van reaches the stop she gets on at, e.g. "06:38" */
            'time' => $this->pickupTime(),
            /** Where she gets off */
            'gets_off' => $this->stopNamed($this->getsOffAt())->name,
            /** When the van reaches it, by the timetable */
            'off_time' => $this->dropTime(),
            /** Her own stop, near home */
            'home_stop_id' => $this->stop_id,
            /** The stop she goes to. Change it with `PUT destination`. */
            'destination_stop_id' => $this->destinationStopId(),
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
            /**
             * Whether she may hold seats on this van for people with her: no, for a day with each paying the mate,
             * or on her plan
             *
             * @var 'off'|'fare'|'plan'
             */
            'extra_seats' => $this->run->vehicle->extra_seats->value,
            /** How many this seat is for, her included: above 1 when people travel with her on her plan */
            'party' => $this->party,
            /** The most a plan may be for on this van, her included */
            'max_party' => 1 + (int) config('subscriptions.extra_seats_max'),
        ];
    }

    private function stopNamed(int $id): Stop
    {
        return $this->run->route->stops->firstWhere('id', $id);
    }
}
