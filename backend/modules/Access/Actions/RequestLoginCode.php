<?php

namespace Modules\Access\Actions;

use App\Support\Contracts\SmsSender;
use App\Support\Phone;
use Illuminate\Support\Facades\Hash;
use Modules\Access\Models\LoginCode;

/** Sends a six-digit code to a phone. Any earlier unused code for that phone stops working. */
class RequestLoginCode
{
    public const MINUTES_VALID = 10;

    public function __construct(private SmsSender $sms) {}

    public function handle(string $phone): void
    {
        $phone = Phone::normalise($phone);
        $code = (string) random_int(100000, 999999);

        LoginCode::query()->where('phone', $phone)->whereNull('consumed_at')->update(['consumed_at' => now()]);

        LoginCode::query()->create([
            'phone' => $phone,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(self::MINUTES_VALID),
        ]);

        $this->sms->send($phone, "Your TrustRide code is {$code}. It expires in ".self::MINUTES_VALID.' minutes.');
    }
}
