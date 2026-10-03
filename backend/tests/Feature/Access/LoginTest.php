<?php

use App\Support\Contracts\SmsSender;
use Modules\Access\Models\Account;
use Modules\Access\Models\LoginCode;
use Modules\Messaging\Models\EventLog;

/** Captures the SMS so the test can read the code. */
function fakeSms(): object
{
    $sms = new class implements SmsSender
    {
        public array $sent = [];

        public function send(string $phone, string $message): void
        {
            $this->sent[] = compact('phone', 'message');
        }

        public function lastCode(): string
        {
            preg_match('/\d{6}/', end($this->sent)['message'], $matches);

            return $matches[0];
        }
    };

    app()->instance(SmsSender::class, $sms);

    return $sms;
}

beforeEach(fn () => syncAccess());

it('logs a new phone number in as a rider', function () {
    $sms = fakeSms();

    $this->postJson('/api/auth/code', ['phone' => '0241234567'])->assertStatus(202);

    expect($sms->sent[0]['phone'])->toBe('+233241234567');

    $response = $this->postJson('/api/auth/verify', ['phone' => '0241234567', 'code' => $sms->lastCode()])
        ->assertOk()
        ->assertJsonPath('account.phone', '+233241234567')
        ->assertJsonPath('account.roles.0.name', 'rider')
        ->assertJsonPath('account.roles.0.screens', 'rider');

    expect($response->json('account.permissions'))
        ->toContain('subscriptions.buy')
        ->not->toContain('trips.pickups.mark');

    $this->withToken($response->json('token'))->getJson('/api/auth/me')->assertOk()->assertJsonPath('account.phone', '+233241234567');
});

it('records account.registered in the event log once', function () {
    $sms = fakeSms();

    $this->postJson('/api/auth/code', ['phone' => '0241234567']);
    $this->postJson('/api/auth/verify', ['phone' => '0241234567', 'code' => $sms->lastCode()])->assertOk();

    $this->postJson('/api/auth/code', ['phone' => '0241234567']);
    $this->postJson('/api/auth/verify', ['phone' => '0241234567', 'code' => $sms->lastCode()])->assertOk();

    expect(EventLog::query()->where('name', 'account.registered')->count())->toBe(1)
        ->and(EventLog::first()->recipients[0]['phone'])->toBe('+233241234567');
});

it('rejects a wrong code and locks the code after five tries', function () {
    $sms = fakeSms();
    $this->postJson('/api/auth/code', ['phone' => '0241234567']);
    $right = $sms->lastCode();
    $wrong = $right === '000000' ? '111111' : '000000';

    foreach (range(1, 5) as $try) {
        $this->postJson('/api/auth/verify', ['phone' => '0241234567', 'code' => $wrong])->assertStatus(422);
    }

    $this->postJson('/api/auth/verify', ['phone' => '0241234567', 'code' => $right])->assertStatus(422);
    expect(Account::count())->toBe(0);
});

it('rejects an expired code and a code used twice', function () {
    $sms = fakeSms();
    $this->postJson('/api/auth/code', ['phone' => '0241234567']);
    $code = $sms->lastCode();

    LoginCode::query()->update(['expires_at' => now()->subMinute()]);
    $this->postJson('/api/auth/verify', ['phone' => '0241234567', 'code' => $code])->assertStatus(422);

    $this->postJson('/api/auth/code', ['phone' => '0241234567']);
    $code = $sms->lastCode();
    $this->postJson('/api/auth/verify', ['phone' => '0241234567', 'code' => $code])->assertOk();
    $this->postJson('/api/auth/verify', ['phone' => '0241234567', 'code' => $code])->assertStatus(422);
});

it('refuses an account that has been switched off', function () {
    $sms = fakeSms();
    Account::query()->create(['phone' => '+233241234567', 'is_active' => false]);

    $this->postJson('/api/auth/code', ['phone' => '0241234567']);
    $this->postJson('/api/auth/verify', ['phone' => '0241234567', 'code' => $sms->lastCode()])->assertStatus(422);
});

it('rejects a phone number that is not Ghanaian', function () {
    $this->postJson('/api/auth/code', ['phone' => '+14155550100'])->assertStatus(422);
});

it('keeps a rider out of driver routes and lets a mate who is also a rider into both', function () {
    $rider = Account::query()->create(['phone' => '+233241111111'])->assignRole('rider');
    $both = Account::query()->create(['phone' => '+233242222222'])->assignRole('rider', 'mate');

    $riderToken = $rider->createToken('test')->plainTextToken;
    $this->withToken($riderToken)->getJson('/api/rider/v1/home')->assertOk();
    $this->withToken($riderToken)->getJson('/api/driver/v1/home')->assertForbidden();

    $this->app['auth']->forgetGuards();

    $bothToken = $both->createToken('test')->plainTextToken;
    $this->withToken($bothToken)->getJson('/api/rider/v1/home')->assertOk();
    $this->withToken($bothToken)->getJson('/api/driver/v1/home')->assertOk();

    expect($both->getAllPermissions()->pluck('name'))->toContain('trips.pickups.mark', 'subscriptions.buy');
});

it('needs a token for anything behind login', function () {
    $this->getJson('/api/auth/me')->assertUnauthorized();
    $this->getJson('/api/rider/v1/home')->assertUnauthorized();
});
