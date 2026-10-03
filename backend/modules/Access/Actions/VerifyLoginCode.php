<?php

namespace Modules\Access\Actions;

use App\Support\Phone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\Access\Events\AccountRegistered;
use Modules\Access\Exceptions\AccountInactive;
use Modules\Access\Exceptions\InvalidLoginCode;
use Modules\Access\Models\Account;
use Modules\Access\Models\LoginCode;

/**
 * Checks a login code. A phone number we've never seen becomes a new account with the rider role.
 * Drivers and mates are created by staff, so their numbers already exist with those roles.
 */
class VerifyLoginCode
{
    public function handle(string $phone, string $code): Account
    {
        $phone = Phone::normalise($phone);

        $loginCode = LoginCode::query()->where('phone', $phone)->whereNull('consumed_at')->latest('id')->first();

        if (! $loginCode || ! $loginCode->isUsable()) {
            throw new InvalidLoginCode;
        }

        if (! Hash::check($code, $loginCode->code_hash)) {
            $loginCode->increment('attempts');

            throw new InvalidLoginCode;
        }

        return DB::transaction(function () use ($phone, $loginCode) {
            $loginCode->update(['consumed_at' => now()]);

            $account = Account::query()->firstOrNew(['phone' => $phone]);
            $isNew = ! $account->exists;

            if (! $isNew && ! $account->is_active) {
                throw new AccountInactive;
            }

            $account->fill([
                'phone_verified_at' => $account->phone_verified_at ?? now(),
                'last_login_at' => now(),
            ])->save();

            if ($isNew) {
                $account->assignRole('rider');
                AccountRegistered::dispatch($account->id, $phone);
            }

            return $account;
        });
    }
}
