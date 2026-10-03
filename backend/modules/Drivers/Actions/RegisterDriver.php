<?php

namespace Modules\Drivers\Actions;

use App\Support\Phone;
use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Access\Actions\EnsureAccountWithRole;
use Modules\Drivers\Models\Driver;

/** Adds a driver and gives his phone number a mobile account with the driver role. He starts as pending. */
class RegisterDriver
{
    public function __construct(private EnsureAccountWithRole $ensureAccount) {}

    public function handle(array $data): Driver
    {
        $phone = Phone::normalise($data['phone']);

        if (Driver::query()->where('phone', $phone)->exists()) {
            throw new DomainException('A driver with that phone number already exists.');
        }

        return DB::transaction(function () use ($data, $phone) {
            $account = $this->ensureAccount->handle($phone, 'driver', $data['name']);

            return Driver::query()->create([...$data, 'phone' => $phone, 'account_id' => $account->id]);
        });
    }
}
