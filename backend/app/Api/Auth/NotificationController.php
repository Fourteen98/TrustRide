<?php

namespace App\Api\Auth;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Access\Actions\SetPreferredChannel;
use Modules\Messaging\Actions\RegisterPushDevice;

/** How Aveo reaches the person logged in: app notifications on this phone, and which way she prefers. */
class NotificationController
{
    /**
     * Turn on app notifications for this phone.
     *
     * The app sends the token the push service gave it, each time it opens. The first time a phone is
     * registered, she is switched to app notifications, because they are free and SMS is not.
     * She can switch back with `PUT preferred-channel`. Login codes always come by SMS.
     */
    public function register(Request $request, RegisterPushDevice $register, SetPreferredChannel $prefer): JsonResponse
    {
        $data = $request->validate([
            /** The Expo push token, e.g. "ExponentPushToken[xxxx]" */
            'token' => ['required', 'string', 'max:255', 'regex:/^Expo(nent)?PushToken\[.+\]$/'],
            'platform' => ['nullable', Rule::in(['ios', 'android'])],
        ]);

        $account = $request->user();
        $device = $register->handle($account->phone, $data['token'], $data['platform'] ?? null);

        if ($device->wasRecentlyCreated && $account->preferred_channel === 'sms') {
            $prefer->handle($account, 'push');
        }

        return response()->json(['account' => new AccountResource($account->fresh()->load('roles'))]);
    }

    /**
     * Choose how to be reached.
     *
     * One of the `channels` on the account. If that way fails, the others are still tried.
     */
    public function prefer(Request $request, SetPreferredChannel $prefer): JsonResponse
    {
        $data = $request->validate(['channel' => ['required', Rule::in(SetPreferredChannel::CHANNELS)]]);

        return response()->json(['account' => new AccountResource($prefer->handle($request->user(), $data['channel'])->load('roles'))]);
    }
}
