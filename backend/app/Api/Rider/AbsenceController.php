<?php

namespace App\Api\Rider;

use App\Api\Rider\Resources\NoticeResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Modules\Subscriptions\Actions\GiveAbsenceNotice;

class AbsenceController
{
    use ResolvesRider;

    /**
     * What happens if she says she won't ride that day.
     *
     * Saves nothing. Show this before she confirms, so she knows whether the day will be credited.
     */
    public function preview(Request $request, GiveAbsenceNotice $notice): JsonResponse
    {
        $data = $request->validate(['date' => ['required', 'date_format:Y-m-d']]);

        $preview = $notice->preview($this->riderOrFail($request), Carbon::parse($data['date']));

        return response()->json([
            'date' => $preview['date']->toDateString(),
            /** True if she is still before the deadline */
            'in_time' => $preview['in_time'],
            /** True if her subscription would get a day longer */
            'credited' => $preview['credited'],
            /** The latest moment notice counts as in time */
            'deadline' => $preview['deadline']->toIso8601String(),
            /** A sentence describing her subscription's rule */
            'rule' => $preview['policy']->label(),
        ]);
    }

    /**
     * Say she won't ride on a day.
     *
     * This can't be taken back from the app. If the day is credited, her subscription ends a day later.
     */
    public function store(Request $request, GiveAbsenceNotice $notice): JsonResponse
    {
        $data = $request->validate(['date' => ['required', 'date_format:Y-m-d']]);

        $given = $notice->handle($this->riderOrFail($request), Carbon::parse($data['date']));

        return response()->json(['notice' => new NoticeResource($given)], 201);
    }
}
