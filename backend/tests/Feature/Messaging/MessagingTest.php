<?php

use App\Support\Recipient;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Modules\Access\Models\Account;
use Modules\Access\Models\Staff;
use Modules\Messaging\Actions\ApplyRules;
use Modules\Messaging\Actions\RenderTemplate;
use Modules\Messaging\Actions\SendMessage;
use Modules\Messaging\Channels\Channel;
use Modules\Messaging\Channels\ChannelManager;
use Modules\Messaging\Channels\ExpoPushChannel;
use Modules\Messaging\Enums\MessageStatus;
use Modules\Messaging\Filament\Resources\MessageResource\Pages\ManageMessages;
use Modules\Messaging\Filament\Resources\MessageRuleResource\Pages\ManageMessageRules;
use Modules\Messaging\Models\EventLog;
use Modules\Messaging\Models\Message;
use Modules\Messaging\Models\MessageRule;
use Modules\Messaging\Models\PushDevice;
use Modules\Riders\Actions\EraseRider;
use Modules\Riders\Actions\RequestDataDeletion;
use Modules\Subscriptions\Actions\ActivateSubscription;
use Modules\Subscriptions\Actions\AnnounceToRun;
use Modules\Subscriptions\Actions\ApproveApplicationWithSeats;
use Modules\Subscriptions\Actions\GiveAbsenceNotice;
use Modules\Subscriptions\Filament\Resources\RunResource\Pages\ManageRuns;
use Modules\Subscriptions\Models\Plan;
use Tests\Fixtures\TestEvent;

/** A channel that remembers what it was asked to send, and can be told to fail. */
function fakeChannel(bool $fails = false): Channel
{
    return new class($fails) implements Channel
    {
        public array $sent = [];

        public function __construct(public bool $fails) {}

        public function send(string $phone, string $body, ?string $name = null): void
        {
            if ($this->fails) {
                throw new RuntimeException('Provider said no');
            }

            $this->sent[] = compact('phone', 'body');
        }
    };
}

function testEvent(array $payload, array $recipients, string $name = 'test.happened'): TestEvent
{
    return new TestEvent($name, $payload, $recipients);
}

beforeEach(function () {
    syncAccess();
    Carbon::setTestNow('2026-10-05 09:00:00');

    $this->sms = fakeChannel();
    $this->whatsapp = fakeChannel();
    $channels = app(ChannelManager::class);
    $channels->use('sms', $this->sms);
    $channels->use('whatsapp', $this->whatsapp);
    $channels->use('push', null);
});

it('fills placeholders and tidies the gaps a missing one leaves', function () {
    $render = app(RenderTemplate::class);

    expect($render->handle('Car {car_number}, {car_colour}. Be at {stop} by {time}.', ['car_number' => 'GT 1', 'car_colour' => 'White', 'stop' => 'Barrier', 'time' => '06:30']))
        ->toBe('Car GT 1, White. Be at Barrier by 06:30.')
        ->and($render->handle('Hello {name} , your {missing} seat.', ['name' => 'Ama', 'seats' => ['a']]))->toBe('Hello Ama, your seat.')
        ->and($render->placeholders('{a} and {b} and {a}'))->toBe(['a', 'b']);
});

it('turns an event into one message per matching recipient, on her preferred channel', function () {
    MessageRule::create(['name' => 'Test', 'event_name' => 'test.happened', 'audience' => 'rider', 'body' => 'Hi {name}, {thing} happened.']);

    event(testEvent(['thing' => 'it'], [
        new Recipient('rider', '+233240000001', 'whatsapp', 'Ama'),
        new Recipient('rider', '+233240000002', 'sms', 'Esi'),
        new Recipient('driver', '+233240000003', 'sms', 'Kwame'), // not the audience
    ]));

    expect(Message::count())->toBe(2)
        ->and($this->whatsapp->sent)->toBe([['phone' => '+233240000001', 'body' => 'Hi Ama, it happened.']])
        ->and($this->sms->sent)->toBe([['phone' => '+233240000002', 'body' => 'Hi Esi, it happened.']])
        ->and(Message::where('recipient_phone', '+233240000001')->first()->status)->toBe(MessageStatus::Sent)
        ->and(Message::where('recipient_phone', '+233240000001')->first()->channel)->toBe('whatsapp');
});

it('falls back to SMS when her preferred channel fails or is not set up', function () {
    MessageRule::create(['name' => 'Test', 'event_name' => 'test.happened', 'body' => 'Hello']);
    $this->whatsapp->fails = true;

    event(testEvent([], [new Recipient('rider', '+233240000001', 'whatsapp'), new Recipient('rider', '+233240000002', 'push')]));

    $viaWhatsapp = Message::where('recipient_phone', '+233240000001')->first();
    $viaPush = Message::where('recipient_phone', '+233240000002')->first();

    expect($viaWhatsapp->status)->toBe(MessageStatus::Sent)->and($viaWhatsapp->channel)->toBe('sms')
        ->and(array_column($viaWhatsapp->attempts, 'channel'))->toBe(['whatsapp', 'push', 'sms'])
        ->and(array_column($viaWhatsapp->attempts, 'ok'))->toBe([false, false, true])
        ->and($viaWhatsapp->attempts[0]['error'])->toBe('Provider said no')
        ->and($viaPush->attempts[0]['error'])->toBe('Not set up')
        ->and($viaPush->channel)->toBe('sms');
});

it('marks a message failed when every channel fails, and can try again later', function () {
    MessageRule::create(['name' => 'Test', 'event_name' => 'test.happened', 'body' => 'Hello']);
    $this->sms->fails = true;
    $this->whatsapp->fails = true;

    event(testEvent([], [new Recipient('rider', '+233240000001', 'sms')]));

    $message = Message::first();
    expect($message->status)->toBe(MessageStatus::Failed)->and($message->lastError())->toBe('Provider said no');

    $this->sms->fails = false;
    app(SendMessage::class)->handle($message);

    expect($message->fresh()->status)->toBe(MessageStatus::Sent)->and($this->sms->sent)->toHaveCount(1);

    // Sending an already sent message does nothing.
    app(SendMessage::class)->handle($message->fresh());
    expect($this->sms->sent)->toHaveCount(1);
});

it('uses only the channel a rule fixes, with no fallback', function () {
    MessageRule::create(['name' => 'Test', 'event_name' => 'test.happened', 'channel' => 'whatsapp', 'body' => 'Hello']);
    $this->whatsapp->fails = true;

    event(testEvent([], [new Recipient('rider', '+233240000001', 'sms')]));

    expect(Message::first()->status)->toBe(MessageStatus::Failed)->and($this->sms->sent)->toBeEmpty();
});

it('never sends twice when the same event is handled twice, and skips rules that are off', function () {
    MessageRule::create(['name' => 'On', 'event_name' => 'test.happened', 'body' => 'Hello']);
    MessageRule::create(['name' => 'Off', 'event_name' => 'test.happened', 'body' => 'Nope', 'is_active' => false]);

    event(testEvent([], [new Recipient('rider', '+233240000001', 'sms')]));

    expect(app(ApplyRules::class)->handle(EventLog::first()))->toBe(0)
        ->and(Message::count())->toBe(1)
        ->and($this->sms->sent)->toHaveCount(1);
});

it('sends the receipt with the car, driver, stop and time when a subscription starts', function () {
    $network = network();
    $run = run($network);
    $application = application($network, 'Ama Owusu', ['stop_id' => $network['stops'][1]->id]);
    $operator = staff(Staff::SUPER_ADMIN);

    app(ApproveApplicationWithSeats::class)->handle($application, [$run->id], $operator);
    app(ActivateSubscription::class)->handle($application->rider, Plan::where('name', '1 month')->first(), $operator);

    $bodies = collect($this->sms->sent)->pluck('body');

    expect($bodies[0])->toBe('Good news Ama Owusu. Your TrustRide seat on Bortianor to Circle from Old Barrier is reserved. Open the app and choose a plan to make it yours.')
        ->and($bodies[1])->toBe("You're in, Ama Owusu. Car {$network['vehicle']->registration_number}, White. Driver Kwame Mensah. Be at Old Barrier by 06:40. Your 1 month plan runs to 3 Nov 2026.")
        ->and($this->sms->sent[1]['phone'])->toBe($application->rider->account->phone);
});

it('lets an operator message everyone riding on a run, leaving out who is away', function () {
    $network = network();
    $run = run($network);
    $operator = staff('operator');
    $month = Plan::where('name', '1 month')->first();

    $riders = collect(['Ama', 'Esi', 'Yaa'])->map(function (string $name) use ($network, $run, $month) {
        $application = application($network, $name);
        app(ApproveApplicationWithSeats::class)->handle($application, [$run->id], staff(Staff::SUPER_ADMIN));
        app(ActivateSubscription::class)->handle($application->rider, $month);

        return $application->rider;
    });

    app(GiveAbsenceNotice::class)->handle($riders[2], Carbon::parse('2026-10-05'));
    $this->sms->sent = [];

    expect(fn () => app(AnnounceToRun::class)->handle($run, '  '))->toThrow(DomainException::class)
        ->and(fn () => app(AnnounceToRun::class)->handle($run, 'Late', null, Carbon::parse('2027-06-01')))->toThrow(DomainException::class, 'Nobody is riding');

    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs($operator, 'platform');

    Livewire::test(ManageRuns::class)
        ->callTableAction('announce', $run, ['date' => '2026-10-05', 'message' => 'The van is 15 minutes late. Sorry.'])
        ->assertHasNoTableActionErrors()
        ->assertNotified('Sent to 2 riders');

    expect(collect($this->sms->sent)->pluck('body')->unique()->all())->toBe(['The van is 15 minutes late. Sorry.'])
        ->and(collect($this->sms->sent)->pluck('phone')->sort()->values()->all())
        ->toBe($riders->take(2)->map(fn ($rider) => $rider->account->phone)->sort()->values()->all())
        ->and(EventLog::where('name', 'run.announcement')->first()->payload['sent_by'])->toBe($operator->id);
});

it('sends login codes through the same SMS channel', function () {
    $this->postJson('/api/auth/code', ['phone' => '0241234567'])->assertStatus(202);

    expect($this->sms->sent[0]['phone'])->toBe('+233241234567')
        ->and($this->sms->sent[0]['body'])->toMatch('/Your TrustRide code is \d{6}/');
});

it('deletes her messages when her data is erased', function () {
    $network = network();
    $application = application($network);
    $admin = staff(Staff::SUPER_ADMIN);
    app(ApproveApplicationWithSeats::class)->handle($application, [run($network)->id], $admin);
    $phone = $application->rider->account->phone;

    expect(Message::where('recipient_phone', $phone)->count())->toBeGreaterThan(0);

    app(EraseRider::class)->handle(app(RequestDataDeletion::class)->handle($application->rider), $admin);

    expect(Message::where('recipient_phone', $phone)->count())->toBe(0);
});

it('lets staff write a rule and retry a failed message in the console', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(staff(Staff::SUPER_ADMIN), 'platform');

    foreach (['message-rules', 'messages', 'events'] as $path) {
        $this->get("/admin/{$path}")->assertOk();
    }

    Livewire::test(ManageMessageRules::class)
        ->callAction('create', ['name' => 'Welcome', 'event_name' => 'account.registered', 'audience' => 'rider', 'channel' => 'sms', 'body' => 'Welcome to TrustRide.'])
        ->assertHasNoActionErrors();

    $this->sms->fails = true;
    $this->postJson('/api/auth/verify', ['phone' => '0241234567', 'code' => '000000']); // no code yet, so no account
    event(testEvent([], [new Recipient('rider', '+233240000009', 'sms')], 'account.registered'));

    $failed = Message::where('recipient_phone', '+233240000009')->first();
    expect($failed->status)->toBe(MessageStatus::Failed);

    $this->sms->fails = false;
    Livewire::test(ManageMessages::class)->callTableAction('retry', $failed);

    expect($failed->fresh()->status)->toBe(MessageStatus::Sent);
});

it('shows an operator the delivery log but not rule editing', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(staff('operator'), 'platform');

    $this->get('/admin/messages')->assertOk();

    Livewire::test(ManageMessageRules::class)->assertTableActionHidden('edit', MessageRule::first());
});

it('sends real SMS through Hubtel when the SMS driver is hubtel, and reports a refusal', function () {
    app(ChannelManager::class)->use('sms', null);
    $manager = new ChannelManager;
    config([
        'messaging.channels.sms.driver' => 'hubtel',
        'messaging.hubtel_sms.client_id' => 'sms-id',
        'messaging.hubtel_sms.client_secret' => 'sms-secret',
        'messaging.hubtel_sms.sender_id' => 'TrustRide',
    ]);
    Http::fake(['sms.hubtel.com/*' => Http::sequence()
        ->push(['messageId' => 'abc', 'status' => 0], 201)
        ->push(['message' => 'Invalid sender'], 400)]);

    $manager->channel('sms')->send('+233241234567', 'Hello Ama');

    Http::assertSent(fn ($request) => $request->url() === 'https://sms.hubtel.com/v1/messages/send'
        && $request['from'] === 'TrustRide' && $request['to'] === '233241234567' && $request['content'] === 'Hello Ama'
        && $request->hasHeader('Authorization', 'Basic '.base64_encode('sms-id:sms-secret')));

    expect(fn () => $manager->channel('sms')->send('+233241234567', 'Again'))->toThrow(RuntimeException::class, 'Invalid sender');
});

it('sends an app notification to every phone she is logged in on, and falls back to SMS when there is none', function () {
    app(ChannelManager::class)->use('push', app(ExpoPushChannel::class));
    app(ChannelManager::class)->use('whatsapp', null);
    Http::fake(['exp.host/*' => Http::response(['data' => [
        ['status' => 'ok', 'id' => 'a'],
        ['status' => 'error', 'message' => 'gone', 'details' => ['error' => 'DeviceNotRegistered']],
    ]])]);
    $account = Account::create(['phone' => '+233240000077'])->assignRole('rider');
    $this->withToken($account->createToken('t')->plainTextToken);

    // Registering her first phone switches her to app notifications. The same phone again changes nothing.
    $this->postJson('/api/auth/push-device', ['token' => 'not-a-token'])->assertStatus(422);
    $this->postJson('/api/auth/push-device', ['token' => 'ExponentPushToken[one]', 'platform' => 'android'])->assertOk()
        ->assertJsonPath('account.preferred_channel', 'push')->assertJsonPath('account.channels', ['sms', 'push']);
    $this->postJson('/api/auth/push-device', ['token' => 'ExponentPushToken[one]'])->assertOk();
    $this->postJson('/api/auth/push-device', ['token' => 'ExponentPushToken[old]'])->assertOk();
    expect(PushDevice::count())->toBe(2);

    event(testEvent([], [new Recipient('rider', '+233240000077', 'push', 'Ama')], 'account.registered'));
    MessageRule::create(['name' => 'Hello', 'event_name' => 'test.hello', 'audience' => 'all', 'body' => 'Hello {name}']);
    event(testEvent([], [new Recipient('rider', '+233240000077', 'push', 'Ama')], 'test.hello'));

    $sent = Message::query()->where('body', 'Hello Ama')->sole();
    expect($sent)->status->toBe(MessageStatus::Sent)->channel->toBe('push')
        ->and($this->sms->sent)->toBeEmpty()
        ->and(PushDevice::query()->pluck('token')->all())->toBe(['ExponentPushToken[one]']); // the dead phone was forgotten
    Http::assertSent(fn ($request) => $request[0]['to'] === 'ExponentPushToken[one]' && $request[0]['body'] === 'Hello Ama' && $request[0]['title'] === 'TrustRide');

    // She switches back to SMS. Then logs out of her only phone: a push-only attempt has nowhere to go, so SMS carries it.
    $this->putJson('/api/auth/preferred-channel', ['channel' => 'sms'])->assertOk()->assertJsonPath('account.preferred_channel', 'sms');
    $this->putJson('/api/auth/preferred-channel', ['channel' => 'pigeon'])->assertStatus(422);
    $this->postJson('/api/auth/logout', ['push_token' => 'ExponentPushToken[one]'])->assertNoContent();
    expect(PushDevice::count())->toBe(0);

    event(testEvent([], [new Recipient('rider', '+233240000077', 'push', 'Ama')], 'test.hello'));
    expect(Message::query()->where('body', 'Hello Ama')->latest('id')->first())->channel->toBe('sms')->status->toBe(MessageStatus::Sent);
});
