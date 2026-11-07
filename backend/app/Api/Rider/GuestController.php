<?php

namespace App\Api\Rider;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Subscriptions\Actions\HoldGuestSeats;
use Modules\Subscriptions\Models\Seat;

class GuestController
{
    use ResolvesRider;

    /**
     * Hold seats for people travelling with her.
     *
     * For one day on one of her seats, on vans where each person pays the fare to the mate. Sending again for the same
     * day changes the number, and 0 lets the seats go. Refused once the van has passed her stop that day.
     */
    public function update(Request $request, HoldGuestSeats $hold): JsonResponse
    {
        $data = $request->validate([
            /** One of her seats, from `GET home` */
            'seat_id' => ['required', 'integer'],
            /** "2026-10-24" */
            'date' => ['required', 'date_format:Y-m-d'],
            /** How many people, not counting her. 0 lets them go. */
            'seats' => ['required', 'integer', 'min:0', 'max:9'],
        ]);

        $seat = Seat::query()->where('rider_id', $this->riderOrFail($request)->id)->findOrFail($data['seat_id']);
        $held = $hold->handle($seat, CarbonImmutable::parse($data['date']), $data['seats']);

        return response()->json([
            /** How many are held for that day now. 0 when let go. */
            'seats' => $held?->seats ?? 0,
            'date' => $data['date'],
        ]);
    }
}
