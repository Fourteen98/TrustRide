<?php

namespace App\Admin\Reports;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Modules\Messaging\Enums\MessageStatus;
use Modules\Messaging\Models\Message;
use Modules\Payments\Enums\PaymentStatus;
use Modules\Payments\Models\Payment;
use Modules\Riders\Enums\ApplicationStatus;
use Modules\Riders\Models\RiderApplication;
use Modules\Subscriptions\Enums\SeatStatus;
use Modules\Subscriptions\Models\AutoRenewal;
use Modules\Subscriptions\Models\PlanPurchase;
use Modules\Subscriptions\Models\Run;
use Modules\Subscriptions\Models\Seat;
use Modules\Subscriptions\Models\Subscription;
use Modules\Support\Models\SupportCase;
use Modules\Trips\Enums\PickupStatus;
use Modules\Trips\Models\Trip;
use Modules\Trips\Models\TripPickup;

/**
 * The numbers that say whether the pilot is working, week by week. Read-only, and it reads across
 * modules, which only a side may do. Weeks run Monday to Sunday.
 *
 * Nothing here is stored. Each number is counted from the records when the page is opened,
 * so it can't drift from them.
 */
class PilotNumbers
{
    /** Where things stand today. */
    public function now(): array
    {
        $today = CarbonImmutable::today();
        $held = (int) Run::query()->where('is_active', true)->sum('seats_held');

        return [
            'riders_riding' => Subscription::query()->covering($today)->distinct()->count('rider_id'),
            'seats_taken' => Seat::query()->whereIn('status', [SeatStatus::Reserved, SeatStatus::Active])->whereIn('run_id', Run::query()->where('is_active', true)->select('id'))->count(),
            'seats_held' => $held,
            'on_direct_debit' => AutoRenewal::query()->where('status', 'active')->count(),
            'applications_waiting' => RiderApplication::query()->where('status', ApplicationStatus::Pending)->count(),
            'open_cases' => SupportCase::query()->where('status', 'open')->count(),
            'paid_not_activated' => PlanPurchase::query()->where('status', 'needs_attention')->count(),
        ];
    }

    /**
     * One row per week, newest first.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function weeks(int $count = 8): Collection
    {
        $thisMonday = CarbonImmutable::today()->startOfWeek();

        return collect(range(0, $count - 1))->map(fn (int $back) => $this->week($thisMonday->subWeeks($back)));
    }

    private function week(CarbonImmutable $monday): array
    {
        $sunday = $monday->addDays(6);
        $from = $monday->startOfDay();
        $to = $sunday->endOfDay();
        $dates = [$monday->toDateString(), $sunday->toDateString()];

        // Subscriptions that ended this week, and how many of those riders had another one to follow.
        $ended = Subscription::query()->whereBetween('ends_on', $dates)->get(['rider_id', 'ends_on']);
        $renewed = $ended->filter(fn (Subscription $subscription) => Subscription::query()
            ->where('rider_id', $subscription->rider_id)
            ->where('starts_on', '>', $subscription->ends_on->toDateString())
            ->exists())->count();

        $trips = Trip::query()->whereBetween('date', $dates)->with('run')->get();
        $grace = (int) config('aveo.report.on_time_minutes');
        $onTime = $trips->filter(fn (Trip $trip) => $trip->started_at->lte(
            CarbonImmutable::parse($trip->date->toDateString().' '.$trip->run->departureTime())->addMinutes($grace)
        ))->count();

        $pickups = TripPickup::query()->whereIn('trip_id', $trips->modelKeys())->get(['status']);
        $messages = Message::query()->where('status', MessageStatus::Sent)->whereBetween('sent_at', [$from, $to])->get(['channel']);

        return [
            'week' => $monday,
            'label' => $monday->format('j M').' to '.$sunday->format('j M'),
            // Anyone with a subscription that covered at least one day of the week, whatever became of it since.
            'riders' => Subscription::query()->where('starts_on', '<=', $dates[1])->where('ends_on', '>=', $dates[0])->distinct()->count('rider_id'),
            'applications' => RiderApplication::query()->whereBetween('created_at', [$from, $to])->count(),
            'money_in' => (int) Payment::query()->where('status', PaymentStatus::Success)->whereBetween('paid_at', [$from, $to])->sum('amount'),
            'payments_failed' => Payment::query()->where('status', PaymentStatus::Failed)->whereBetween('created_at', [$from, $to])->count(),
            'ended' => $ended->count(),
            'renewed' => $renewed,
            'trips_due' => $this->tripsDue($monday),
            'trips_run' => $trips->count(),
            'trips_on_time' => $onTime,
            'picked_up' => $pickups->where('status', PickupStatus::PickedUp)->count(),
            'missed' => $pickups->where('status', PickupStatus::Missed)->count(),
            'cases' => SupportCase::query()->whereBetween('created_at', [$from, $to])->count(),
            'incidents' => SupportCase::query()->where('is_incident', true)->whereBetween('created_at', [$from, $to])->count(),
            'sms' => $messages->where('channel', 'sms')->count(),
            'push' => $messages->where('channel', 'push')->count(),
        ];
    }

    /**
     * How many trips should have gone out that week, up to today. It uses the runs as they are now,
     * so for a week before a run was added or changed it is a close guess, not a record.
     */
    private function tripsDue(CarbonImmutable $monday): int
    {
        $today = CarbonImmutable::today();
        $runs = Run::query()->where('is_active', true)->get();

        return collect(range(0, 6))
            ->map(fn (int $day) => $monday->addDays($day))
            ->filter(fn (CarbonImmutable $day) => $day->lte($today))
            ->sum(fn (CarbonImmutable $day) => $runs->filter(fn (Run $run) => $run->created_at->startOfDay()->lte($day) && $run->runsOn($day))->count());
    }
}
