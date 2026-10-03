<?php

namespace Modules\Access\Actions;

use App\Support\Phone;
use Modules\Access\Models\Account;

/**
 * Makes sure a phone number has a mobile account that holds a role. Other modules use this
 * when staff add a driver or a mate, so the person can log in to the app straight away.
 * It never removes roles the account already has.
 */
class EnsureAccountWithRole
{
    public function handle(string $phone, string $role, ?string $name = null): Account
    {
        $account = Account::query()->firstOrCreate(
            ['phone' => Phone::normalise($phone)],
            ['name' => $name],
        );

        if ($name !== null && $account->name === null) {
            $account->update(['name' => $name]);
        }

        if (! $account->hasRole($role)) {
            $account->assignRole($role);
        }

        return $account;
    }
}
