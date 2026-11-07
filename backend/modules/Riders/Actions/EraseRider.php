<?php

namespace Modules\Riders\Actions;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Access\Actions\RemoveRoleFromAccount;
use Modules\Access\Models\Staff;
use Modules\Riders\Events\RiderErased;
use Modules\Riders\Models\DataDeletionRequest;
use Modules\Riders\Models\Rider;

/**
 * Deletes a rider for good. This cannot be undone.
 *
 * Deleting the rider row removes everything that hangs off it in the database: her applications,
 * seats, subscriptions and absence notices. Her mobile account goes too, unless she is also a
 * driver or a mate, in which case only the rider role is removed. The RiderErased event then tells
 * the other modules to remove copies they hold under her phone number, such as the event log.
 */
class EraseRider
{
    public function __construct(private RemoveRoleFromAccount $removeRole) {}

    public function handle(DataDeletionRequest $request, Staff $by): DataDeletionRequest
    {
        if (! $request->isPending() || $request->rider === null) {
            throw new DomainException('This request has already been dealt with.');
        }

        return DB::transaction(fn () => $this->erase($request->rider, $request, $by));
    }

    /**
     * The same deletion, started by the retention job and not by her: she stopped riding longer ago
     * than Aveo keeps data. A completed request is written so there is a record that it happened,
     * and why. Nobody's name is on it, because no person did it.
     */
    public function forRetention(Rider $rider): DataDeletionRequest
    {
        return DB::transaction(function () use ($rider) {
            // If she had asked and nobody had acted yet, that request is the one that gets completed.
            $request = DataDeletionRequest::query()->where('rider_id', $rider->id)->where('status', DataDeletionRequest::PENDING)->first()
                ?? DataDeletionRequest::query()->create(['rider_id' => $rider->id, 'requested_at' => now(), 'reason' => DataDeletionRequest::RETENTION]);

            return $this->erase($rider, $request, null);
        });
    }

    private function erase(Rider $rider, DataDeletionRequest $request, ?Staff $by): DataDeletionRequest
    {
        $account = $rider->account;
        $phone = $account->phone;

        $rider->delete();
        $this->removeRole->handle($account, 'rider');

        $request->update(['status' => DataDeletionRequest::COMPLETED, 'completed_at' => now(), 'completed_by' => $by?->id]);

        RiderErased::dispatch($phone, $request->id);

        return $request;
    }
}
