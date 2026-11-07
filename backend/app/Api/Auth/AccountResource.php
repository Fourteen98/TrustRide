<?php

namespace App\Api\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Access\Models\Account;
use Modules\Drivers\Models\Driver;
use Modules\Messaging\Channels\ChannelManager;

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
            /** @var 'push'|'sms'|'whatsapp' How she asked to be reached. The others are still tried if it fails. */
            'preferred_channel' => $this->preferred_channel,
            /** @var list<'push'|'sms'|'whatsapp'> The ways of reaching her that are switched on at Aveo, to offer as choices. */
            'channels' => $this->channelsSetUp(),
            'roles' => $this->roles->map(fn ($role) => [
                'name' => $role->name,
                'label' => $role->label,
                'screens' => $role->screens,
            ])->values(),
            /** @var list<'ride'|'ox'> The work a driver does for Aveo, so the app shows only his screens. Empty for anyone not a driver. */
            'work_types' => Driver::query()->where('account_id', $this->id)->first()?->work_types->map->value->values()->all() ?? [],
            /** @var list<string> Every permission the account holds, e.g. subscriptions.buy */
            'permissions' => $this->getAllPermissions()->pluck('name')->sort()->values(),
        ];
    }

    /** @return list<'push'|'sms'|'whatsapp'> */
    private function channelsSetUp(): array
    {
        $channels = app(ChannelManager::class);

        return array_values(array_filter(ChannelManager::CHANNELS, fn (string $key) => $channels->isSetUp($key)));
    }
}
