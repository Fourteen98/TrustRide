<?php

namespace App\Api\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Access\Models\Account;

/**
 * What the mobile app gets after login. It builds its home screen from "roles"
 * (which screen sets to show) and hides buttons using "permissions".
 * The server still checks every request.
 *
 * @mixin Account
 */
class AccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'phone' => $this->phone,
            'name' => $this->name,
            'preferred_channel' => $this->preferred_channel,
            'roles' => $this->roles->map(fn ($role) => [
                'name' => $role->name,
                'label' => $role->label,
                'screens' => $role->screens,
            ])->values(),
            /** @var list<string> Every permission the account holds, e.g. subscriptions.buy */
            'permissions' => $this->getAllPermissions()->pluck('name')->sort()->values(),
        ];
    }
}
