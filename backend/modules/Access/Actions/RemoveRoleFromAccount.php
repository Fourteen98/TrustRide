<?php

namespace Modules\Access\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Access\Models\Account;
use Modules\Access\Models\LoginCode;

/**
 * Takes a role away from a mobile account. If that was its last role, the account itself is deleted,
 * with its login tokens and codes. A mate who was also a rider keeps his account and stays a mate.
 *
 * @return bool True if the account was deleted
 */
class RemoveRoleFromAccount
{
    public function handle(Account $account, string $role): bool
    {
        return DB::transaction(function () use ($account, $role) {
            if ($account->hasRole($role)) {
                $account->removeRole($role);
            }

            if ($account->roles()->exists()) {
                return false;
            }

            $account->tokens()->delete();
            LoginCode::query()->where('phone', $account->phone)->delete();
            $account->delete();

            return true;
        });
    }
}
