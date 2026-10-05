<?php

use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Modules\Access\Models\Account;
use Modules\Access\Models\Staff;
use Modules\Messaging\Models\Message;
use Modules\Payments\Actions\ApplyMandateOutcome;
use Modules\Payments\Actions\ApplyOutcome;
use Modules\Payments\Actions\StartPayment;
use Modules\Payments\Enums\MandateStatus;
use Modules\Payments\Jobs\SettleFakeMandate;
use Modules\Payments\Jobs\SettleFakePayment;
use Modules\Payments\Models\Payment;
use Modules\Payments\Models\PaymentMandate;
use Modules\Riders\Actions\EraseRider;
use Modules\Riders\Actions\RequestDataDeletion;
use Modules\Riders\Actions\SubmitApplication;
use Modules\Riders\Models\Rider;
use Modules\Subscriptions\Actions\ApproveApplicationWithSeats;
use Modules\Subscriptions\Actions\RunDailyUpkeep;
use Modules\Subscriptions\Enums\AutoRenewalStatus;
use Modules\Subscriptions\Filament\Resources\AutoRenewalResource\Pages\ManageAutoRenewals;
use Modules\Subscriptions\Models\AutoRenewal;
use Modules\Subscriptions\Models\Plan;
use Modules\Subscriptions\Models\Subscription;

/*
 * Direct debit on the fake gateway. Both fake jobs are held back, so each test decides when
 * "she approves on her phone" and when "the money arrives".
 */
beforeEach(function () {
    syncAccess();
    Carbon::setTestNow('2026-10-05 09:00:00');
    config(['payments.driver' => 'fake']);
    Queue::fake([SettleFakeMandate::class, SettleFakePayment::class]);

    $this->network = network();
    $this->run = run($this->network);
    $this->operator = staff('operator');
    $this->account = Account::create(['phone' => '+233241234567'])->assignRole('rider');
    $this->withToken($this->account->createToken('test')->plainTextToken);
    $this->plan = Plan::query()->where('duration_days', 30)->firstOrFail();

    $application = app(SubmitApplication::class)->handle($this->account, [
        'name' => 'Ama Owusu', 'route_id' => $this->network['route']->id,
        'stop_id' => $this->network['stops'][1]->id, 'morning_time' => '06:30',
    ]);
    app(ApproveApplicationWithSeats::class)->handle($application, [$this->run->id], $this->operator);
    $this->rider = $application->rider;
});

function setUpAuto(array $overrides = [])
{
    return test()->postJson('/api/rider/v1/auto-renewal', ['plan_id' => test()->plan->id, 'network' => 'mtn', ...$overrides]);
}

function approveMandate(bool $approved = true): void
{
    app(ApplyMandateOutcome::class)->handle(PaymentMandate::query()->latest('id')->firstOrFail(), $approved, 'fake', null, 'Declined on the phone.');
}

function moneyArrives(bool $paid = true): void
{
    app(ApplyOutcome::class)->handle(Payment::query()->latest('id')->firstOrFail(), $paid, 'FAKE', 'fake', null, $paid ? null : 'Insufficient funds');
}

it('asks her to approve once, then charges and activates with no prompt', function () {
    setUpAuto()->assertCreated()
        ->assertJsonPath('auto_renewal.status', 'pending')
        ->assertJsonPath('auto_renewal.needs_code', false)
        ->assertJsonPath('auto_renewal.wallet', '4567')
        ->assertJsonPath('auto_renewal.amount_label', 'GHS 60');

    expect(Payment::count())->toBe(0); // nothing is charged until she approves
    $this->getJson('/api/rider/v1/home')->assertJsonPath('auto_renewal.status', 'pending');

    approveMandate();

    $this->getJson('/api/rider/v1/auto-renewal')->assertJsonPath('auto_renewal.status', 'active');
    $payment = Payment::query()->sole();
    expect($payment)->mandate_id->toBe(PaymentMandate::query()->sole()->id)->amount->toBe(6000)
        ->and(Subscription::count())->toBe(0);
    $this->getJson('/api/rider/v1/purchase')->assertJsonPath('purchase.status', 'pending');

    moneyArrives();

    $this->getJson('/api/rider/v1/home')->assertJsonPath('subscription.plan', '1 month')->assertJsonPath('auto_renewal.status', 'active');
    expect(Message::query()->where('body', 'like', '%automatic renewal is on%')->count())->toBe(1)
        ->and(Message::query()->where('body', 'like', 'You\'re in, Ama Owusu%')->count())->toBe(1);
});

it('renews by itself the day before the plan ends, after telling her, and keeps her subscription unbroken', function () {
    setUpAuto();
    approveMandate();
    moneyArrives();
    $first = Subscription::query()->sole();
    $upkeep = fn () => app(RunDailyUpkeep::class)->handle();

    // Mid-plan: nothing is due.
    $this->travelTo($first->ends_on->subDays(10)->setTime(0, 10));
    expect($upkeep()['charged'])->toBe(0)->and(Payment::count())->toBe(1);

    // Three days before: she is told what will be taken, not asked to renew.
    $this->travelTo($first->ends_on->subDays(3)->setTime(0, 10));
    $upkeep();
    expect(Message::query()->where('body', 'like', '%We will take GHS 60 from your MTN MoMo wallet ending 4567%')->count())->toBe(1)
        ->and(Message::query()->where('body', 'like', '%Renew to keep your seat%')->count())->toBe(0);

    // The day before: charged once, however often the job runs.
    $this->travelTo($first->ends_on->subDay()->setTime(0, 10));
    expect($upkeep()['charged'])->toBe(1)->and($upkeep()['charged'])->toBe(0)->and(Payment::count())->toBe(2);
    moneyArrives();

    $subscriptions = Subscription::query()->orderBy('starts_on')->get();
    expect($subscriptions)->toHaveCount(2)
        ->and($subscriptions[1]->starts_on->toDateString())->toBe($first->ends_on->addDay()->toDateString());

    // Paid ahead now, so the next days do nothing.
    $this->travelTo($first->ends_on->setTime(0, 10));
    expect($upkeep()['charged'])->toBe(0);
});

it('tries again each day when a charge fails, and switches itself off after three', function () {
    setUpAuto();
    approveMandate();
    moneyArrives();
    $ends = Subscription::query()->sole()->ends_on;

    foreach ([1, 0, -1] as $i => $daysBefore) {
        $this->travelTo($ends->subDays($daysBefore)->setTime(0, 10));
        app(RunDailyUpkeep::class)->handle();
        moneyArrives(false);

        expect(AutoRenewal::query()->sole()->failures)->toBe($i + 1);
    }

    $renewal = AutoRenewal::query()->sole();
    expect($renewal->status)->toBe(AutoRenewalStatus::Off)
        ->and(PaymentMandate::query()->sole()->status)->toBe(MandateStatus::Cancelled)
        ->and(Message::query()->where('body', 'like', '%automatic renewal is now off%')->count())->toBe(1);

    $this->travelTo($ends->addDays(2)->setTime(0, 10));
    expect(app(RunDailyUpkeep::class)->handle()['charged'])->toBe(0)->and(Payment::count())->toBe(4);
    $this->getJson('/api/rider/v1/home')->assertJsonPath('auto_renewal', null);
});

it('lets her stop it at any time, and never charges a mandate after that', function () {
    setUpAuto();
    approveMandate();
    moneyArrives();
    $mandate = PaymentMandate::query()->sole();

    $this->deleteJson('/api/rider/v1/auto-renewal')->assertOk()->assertJsonPath('auto_renewal.status', 'off');

    expect($mandate->fresh()->status)->toBe(MandateStatus::Cancelled)
        ->and(Subscription::query()->sole()->status->value)->toBe('active') // what she paid for is untouched
        ->and(Message::query()->where('body', 'like', '%now off%')->count())->toBe(0); // she did it herself, so no text
    expect(fn () => app(StartPayment::class)->handle('x', 1, 100, '+233241234567', 'mtn', 'x', $mandate->fresh()))
        ->toThrow(DomainException::class);

    $this->travelTo(Subscription::query()->sole()->ends_on->subDay()->setTime(0, 10));
    expect(app(RunDailyUpkeep::class)->handle()['charged'])->toBe(0);
});

it('switches off when she declines, and lets her replace the wallet by asking again', function () {
    setUpAuto();
    approveMandate(false);
    $this->getJson('/api/rider/v1/auto-renewal')->assertJsonPath('auto_renewal.status', 'off');
    expect(Payment::count())->toBe(0);

    setUpAuto(['phone' => '0201112222', 'network' => 'telecel'])->assertCreated()->assertJsonPath('auto_renewal.wallet', '2222')->assertJsonPath('auto_renewal.network', 'Telecel Cash');
    setUpAuto()->assertCreated(); // changes her mind again before approving

    expect(AutoRenewal::count())->toBe(1)
        ->and(PaymentMandate::query()->where('status', MandateStatus::Pending)->count())->toBe(1)
        ->and(PaymentMandate::query()->where('status', MandateStatus::Cancelled)->count())->toBe(1);

    // A late answer about the one she replaced changes nothing.
    app(ApplyMandateOutcome::class)->handle(PaymentMandate::query()->where('network', 'telecel')->first(), true, 'fake');
    expect(AutoRenewal::query()->sole()->status)->toBe(AutoRenewalStatus::Pending);
});

it('takes a texted code on networks that confirm that way', function () {
    $this->account->update(['phone' => '+233241231111']);

    setUpAuto()->assertCreated()->assertJsonPath('auto_renewal.needs_code', true)->assertJsonPath('auto_renewal.code_prefix', 'TEST');

    $this->postJson('/api/rider/v1/auto-renewal/code', ['code' => '0000'])->assertStatus(422)->assertJsonPath('message', 'That code is not right.');
    $this->postJson('/api/rider/v1/auto-renewal/code', ['code' => '1234'])->assertOk();
    Queue::assertPushed(SettleFakeMandate::class);
});

it('refuses where it could not work: no seat, or a network with no direct debit', function () {
    setUpAuto(['network' => 'airteltigo'])->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'not available on that network'));
    expect(AutoRenewal::count())->toBe(0)->and(PaymentMandate::count())->toBe(0);

    $other = Account::create(['phone' => '+233209990000'])->assignRole('rider');
    app('auth')->forgetGuards();
    $this->withToken($other->createToken('t')->plainTextToken);
    setUpAuto()->assertStatus(422)->assertJsonPath('message', 'Apply for a seat first.');
});

it('withdraws the mandate when her data is erased, and lets an operator turn one off', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    setUpAuto();
    approveMandate();
    moneyArrives();

    $this->actingAs($this->operator, 'platform');
    $this->get('/admin/auto-renewals')->assertOk()->assertSee('Ama Owusu');
    Livewire::test(ManageAutoRenewals::class)->callTableAction('stop', AutoRenewal::query()->sole());
    expect(AutoRenewal::query()->sole()->status)->toBe(AutoRenewalStatus::Off);

    setUpAuto();
    approveMandate();
    app(EraseRider::class)->handle(app(RequestDataDeletion::class)->handle(Rider::query()->sole()), staff(Staff::SUPER_ADMIN));

    expect(AutoRenewal::count())->toBe(0)
        ->and(PaymentMandate::query()->whereNotNull('phone')->count())->toBe(0)
        ->and(PaymentMandate::query()->whereIn('status', [MandateStatus::Active, MandateStatus::Pending])->count())->toBe(0);
});

it('speaks Hubtel\'s direct debit: request, callback, charge without a prompt, cancel', function () {
    config(['payments.driver' => 'hubtel', 'payments.hubtel.username' => 'u', 'payments.hubtel.api_key' => 'k', 'payments.hubtel.merchant_account_number' => '2012345']);
    Http::fake([
        'preapproval.hubtel.com/*/preapproval/initiate' => Http::response(['message' => 'Success', 'code' => '2000', 'data' => [
            'hubtelPreApprovalId' => 'pre-1', 'clientReferenceId' => 'x', 'verificationType' => 'USSD', 'otpPrefix' => null, 'preapprovalStatus' => 'PENDING',
        ]]),
        'preapproval.hubtel.com/*/cancel' => Http::response(['message' => 'Success']),
        'rmp.hubtel.com/*' => Http::response(['ResponseCode' => '0001', 'Data' => ['TransactionId' => 'tx-1']]),
    ]);

    setUpAuto()->assertCreated()->assertJsonPath('auto_renewal.needs_code', false);
    $mandate = PaymentMandate::query()->sole();

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/api/v2/merchant/2012345/preapproval/initiate')
        && $request['clientReferenceId'] === $mandate->reference
        && $request['customerMsisdn'] === '233241234567'
        && $request['channel'] === 'mtn-gh-direct-debit'
        && str_ends_with($request['callbackUrl'], '/api/webhooks/hubtel/preapproval'));

    $callback = ['CustomerMsisdn' => '233241234567', 'VerificationType' => 'USSD', 'PreapprovalStatus' => 'APPROVED', 'HubtelPreapprovalId' => 'pre-1', 'ClientReferenceId' => $mandate->reference];
    $this->postJson('/api/webhooks/hubtel/preapproval', ['ClientReferenceId' => 'TR-M-MADEUP', 'PreapprovalStatus' => 'APPROVED'])->assertOk();
    expect($mandate->fresh()->status)->toBe(MandateStatus::Pending);

    $this->postJson('/api/webhooks/hubtel/preapproval', $callback)->assertOk();
    $this->postJson('/api/webhooks/hubtel/preapproval', $callback)->assertOk();

    expect($mandate->fresh()->status)->toBe(MandateStatus::Active)->and(Payment::count())->toBe(1);
    Http::assertSent(fn ($request) => str_contains($request->url(), '/receive/mobilemoney') && $request['Channel'] === 'mtn-gh-direct-debit');

    $this->deleteJson('/api/rider/v1/auto-renewal')->assertOk();
    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/preapproval/233241234567/cancel'));
});

it('closes a direct debit request nobody answered', function () {
    setUpAuto();
    $this->travel(20)->minutes();

    $this->artisan('payments:reconcile')->assertSuccessful();

    expect(PaymentMandate::query()->sole()->status)->toBe(MandateStatus::Failed)
        ->and(AutoRenewal::query()->sole()->status)->toBe(AutoRenewalStatus::Off);
});
