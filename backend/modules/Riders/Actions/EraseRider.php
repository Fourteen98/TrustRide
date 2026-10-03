<?php

namespace Modules\Riders\Actions;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Access\Actions\RemoveRoleFromAccount;
use Modules\Access\Models\Staff;
use Modules\Riders\Events\RiderErased;
use Modules\Riders\Models\DataDeletionRequest;

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

        return DB::transaction(function () use ($request, $by) {
            $rider = $request->rider;
            $account = $rider->account;
            $phone = $account->phone;

            $rider->delete();
            $this->removeRole->handle($account, 'rider');

            $request->update(['status' => DataDeletionRequest::COMPLETED, 'completed_at' => now(), 'completed_by' => $by->id]);

            RiderErased::dispatch($phone, $request->id);

            return $request;
        });
    }
}
