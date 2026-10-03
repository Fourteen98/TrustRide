<?php

namespace Modules\Drivers\Actions;

use App\Support\Phone;
use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Access\Actions\EnsureAccountWithRole;
use Modules\Drivers\Models\Driver;
use Modules\Drivers\Models\Mate;

/** Adds a mate to a driver and gives his phone number a mobile account with the mate role. */
class RegisterMate
{
    public function __construct(private EnsureAccountWithRole $ensureAccount) {}

    public function handle(Driver $driver, string $name, string $phone): Mate
    {
        $phone = Phone::normalise($phone);

        if (Mate::query()->where('phone', $phone)->exists()) {
            throw new DomainException('A mate with that phone number already exists.');
        }

        return DB::transaction(function () use ($driver, $name, $phone) {
            $account = $this->ensureAccount->handle($phone, 'mate', $name);

            return Mate::query()->create([
                'organisation_id' => $driver->organisation_id,
                'driver_id' => $driver->id,
                'account_id' => $account->id,
                'name' => $name,
                'phone' => $phone,
            ]);
        });
    }
}
