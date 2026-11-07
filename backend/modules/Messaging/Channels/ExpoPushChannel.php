<?php

namespace Modules\Messaging\Channels;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Messaging\Models\PushDevice;
use RuntimeException;

/**
 * App notifications through Expo's push service, which passes them to Apple and Google. Free.
 *
 *   POST https://exp.host/--/api/v2/push/send   [{to, title, body, sound}]
 *
 * A notification goes to every phone the person is logged in on. If she has none, or none took it,
 * this throws, so delivery falls back to the next channel and she still gets an SMS.
 * A phone the service says no longer has the app is forgotten.
 *
 * "Accepted" here means Expo took it, not that the phone showed it.
 */
class ExpoPushChannel implements Channel
{
    public function send(string $phone, string $body, ?string $name = null): void
    {
        $devices = PushDevice::query()->where('phone', $phone)->get();

        if ($devices->isEmpty()) {
            throw new RuntimeException('No phone with app notifications on.');
        }

        $request = Http::acceptJson()->asJson()->timeout(15);

        if ($token = config('messaging.expo_push.access_token')) {
            $request = $request->withToken($token);
        }

        $response = $request->post(config('messaging.expo_push.endpoint'), $devices->map(fn (PushDevice $device) => [
            'to' => $device->token,
            'title' => 'Aveo',
            'body' => $body,
            'sound' => 'default',
            'priority' => 'high',
        ])->values()->all());

        if (! $response->successful()) {
            throw new RuntimeException("Expo push error: HTTP {$response->status()}");
        }

        $accepted = 0;

        // One answer per phone, in the order they were sent.
        foreach ($response->json('data') ?? [] as $index => $ticket) {
            if (($ticket['status'] ?? null) === 'ok') {
                $accepted++;

                continue;
            }

            if (($ticket['details']['error'] ?? null) === 'DeviceNotRegistered' && isset($devices[$index])) {
                $devices[$index]->delete();
            }

            Log::info('Expo push refused a phone', ['phone' => $phone, 'error' => $ticket['details']['error'] ?? $ticket['message'] ?? 'unknown']);
        }

        if ($accepted === 0) {
            throw new RuntimeException('No phone accepted the notification.');
        }
    }
}
