<?php

namespace App\Api\Rider;

use DomainException;
use Illuminate\Http\Request;
use Modules\Riders\Models\Rider;

/**
 * Every rider endpoint works on the logged-in account's own rider record and nothing else.
 * There is no rider id in any URL, so one rider can never reach another's data.
 */
trait ResolvesRider
{
    protected function rider(Request $request): ?Rider
    {
        return Rider::query()->where('account_id', $request->user()->id)->first();
    }

    protected function riderOrFail(Request $request): Rider
    {
        return $this->rider($request) ?? throw new DomainException('Apply for a seat first.');
    }
}
