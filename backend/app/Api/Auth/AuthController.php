<?php

namespace App\Api\Auth;

use App\Support\Phone;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Access\Actions\RequestLoginCode;
use Modules\Access\Actions\VerifyLoginCode;

/** Login for the mobile app. One login for every role. */
class AuthController
{
    /**
     * Send a login code.
     *
     * Texts a six-digit code to the phone number. The code lasts 10 minutes. Asking again cancels the earlier code.
     *
     * @unauthenticated
     */
    public function requestCode(Request $request, RequestLoginCode $requestLoginCode): JsonResponse
    {
        $data = $request->validate(['phone' => ['required', 'string', $this->ghanaPhone()]]);

        $requestLoginCode->handle($data['phone']);

        return response()->json(['message' => 'We sent a code to that number.'], 202);
    }

    /**
     * Log in with the code.
     *
     * Returns a token and the account with its roles and permissions. A phone number we have not seen
     * becomes a new account with the rider role. The app builds its home screen from `roles[].screens`.
     *
     * @unauthenticated
     */
    public function verifyCode(Request $request, VerifyLoginCode $verifyLoginCode): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', $this->ghanaPhone()],
            'code' => ['required', 'digits:6'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $account = $verifyLoginCode->handle($data['phone'], $data['code']);

        return response()->json([
            'token' => $account->createToken($data['device_name'] ?? 'mobile')->plainTextToken,
            'account' => new AccountResource($account),
        ]);
    }

    /**
     * The logged-in account.
     *
     * Call this when the app opens, to refresh roles and permissions.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json(['account' => new AccountResource($request->user())]);
    }

    /**
     * Log out.
     *
     * Deletes the token that made this request.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(status: 204);
    }

    private function ghanaPhone(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            if (! is_string($value) || ! Phone::isValid($value)) {
                $fail('Enter a valid Ghana phone number.');
            }
        };
    }
}
