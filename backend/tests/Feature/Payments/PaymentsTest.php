<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Modules\Access\Models\Staff;
use Modules\Payments\Actions\ApplyOutcome;
use Modules\Payments\Actions\ReconcilePayment;
use Modules\Payments\Actions\ResolveManually;
use Modules\Payments\Actions\StartPayment;
use Modules\Payments\Enums\PaymentStatus;
use Modules\Payments\Events\PaymentFailed;
use Modules\Payments\Events\PaymentSucceeded;
use Modules\Payments\Filament\Resources\PaymentResource\Pages\ManagePayments;
use Modules\Payments\Gateways\HubtelGateway;
use Modules\Payments\Models\Payment;

beforeEach(function () {
    config([
        'payments.driver' => 'hubtel',
        'payments.hubtel.username' => 'user-name-1',
        'payments.hubtel.api_key' => 'secret-key-1',
        'payments.hubtel.merchant_account_number' => '2012345',
    ]);
    Event::fake([PaymentSucceeded::class, PaymentFailed::class]);
});

function hubtelAccepts(): void
{
    Http::fake(['rmp.hubtel.com/*' => Http::response([
        'Message' => 'Transaction pending. Expect callback request for final state',
        'ResponseCode' => '0001',
        'Data' => ['TransactionId' => 'hub-tx-1', 'ClientReference' => 'x', 'Amount' => 60],
    ])]);
}

function startHubtelPayment(int $amount = 6000): Payment
{
    hubtelAccepts();

    return app(StartPayment::class)->handle('plan_purchase', 7, $amount, '+233241234567', 'mtn', 'Aveo 1 month plan');
}

function callback(Payment $payment, string $code = '0000', array $data = [])
{
    return test()->postJson('/api/webhooks/hubtel/payment', [
        'ResponseCode' => $code,
        'Message' => $code === '0000' ? 'success' : 'FAILED',
        'Data' => ['ClientReference' => $payment->reference, 'TransactionId' => 'hub-tx-1', 'Amount' => $payment->amount / 100, ...$data],
    ]);
}

it('asks Hubtel to prompt the phone, in cedis, with our own reference', function () {
    $payment = startHubtelPayment();

    expect($payment->status)->toBe(PaymentStatus::Initiated)
        ->and($payment->reference)->toStartWith('TR-')
        ->and($payment->provider_transaction_id)->toBe('hub-tx-1');

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/merchantaccount/merchants/2012345/receive/mobilemoney')
        && $request['CustomerMsisdn'] === '233241234567'
        && $request['Channel'] === 'mtn-gh'
        && $request['Amount'] == 60.0
        && $request['ClientReference'] === $payment->reference
        && str_ends_with($request['PrimaryCallbackUrl'], '/api/webhooks/hubtel/payment')
        && $request->hasHeader('Authorization', 'Basic '.base64_encode('user-name-1:secret-key-1')));
});

it('refuses an unknown network before anything is saved', function () {
    expect(fn () => app(StartPayment::class)->handle('plan_purchase', 7, 6000, '+233241234567', 'glo', 'x'))
        ->toThrow(DomainException::class, 'network');

    expect(Payment::count())->toBe(0);
});

it('keeps a failed record and says so when Hubtel refuses the request', function () {
    Http::fake(['rmp.hubtel.com/*' => Http::response(['ResponseCode' => '4000', 'Message' => 'Validation error'], 400)]);

    expect(fn () => app(StartPayment::class)->handle('plan_purchase', 7, 6000, '+233241234567', 'mtn', 'x'))
        ->toThrow(DomainException::class, 'not been charged');

    expect(Payment::first())->status->toBe(PaymentStatus::Failed)->failure_reason->toBe('Validation error');
    Event::assertNotDispatched(PaymentFailed::class);
});

it('marks a payment paid from the callback, once, whatever the capital letters', function () {
    $payment = startHubtelPayment();

    callback($payment)->assertOk();
    // Hubtel's docs show camelCase. A second, differently spelled copy changes nothing.
    $this->postJson('/api/webhooks/hubtel/payment', ['responseCode' => '0000', 'data' => ['clientReference' => $payment->reference]])->assertOk();

    expect($payment->fresh())->status->toBe(PaymentStatus::Success)->paid_at->not->toBeNull();
    Event::assertDispatchedTimes(PaymentSucceeded::class, 1);
});

it('marks a payment failed from the callback with the reason', function () {
    $payment = startHubtelPayment();

    callback($payment, '2001')->assertOk();

    expect($payment->fresh())->status->toBe(PaymentStatus::Failed)->failure_reason->toBe('FAILED');
    Event::assertDispatched(PaymentFailed::class, fn ($event) => $event->payableType === 'plan_purchase' && $event->payableId === 7);
});

it('ignores a pending callback and a reference it does not know, and still answers 200', function () {
    $payment = startHubtelPayment();

    callback($payment, '0001')->assertOk();
    $this->postJson('/api/webhooks/hubtel/payment', ['ResponseCode' => '0000', 'Data' => ['ClientReference' => 'TR-MADEUP']])->assertOk();
    $this->postJson('/api/webhooks/hubtel/payment', [])->assertOk();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Initiated);
    Event::assertNotDispatched(PaymentSucceeded::class);
});

it('holds a callback that says paid for less than the price', function () {
    $payment = startHubtelPayment();

    callback($payment, '0000', ['Amount' => 1])->assertOk();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Initiated);
});

it('counts money that arrives after a payment was marked failed, and never undoes a paid one', function () {
    $payment = startHubtelPayment();
    $apply = app(ApplyOutcome::class);

    expect($apply->handle($payment, false, null, 'status_check', null, 'Timed out'))->toBeTrue()
        ->and($apply->handle($payment, true, 'late-1', 'callback'))->toBeTrue()
        ->and($apply->handle($payment, false, null, 'callback'))->toBeFalse()
        ->and($payment->fresh())->status->toBe(PaymentStatus::Success)->failure_reason->toBeNull();
});

it('finds a paid payment by status check when the callback never came', function () {
    $payment = startHubtelPayment();
    Http::fake(['api-txnstatus.hubtel.com/*' => Http::response(['message' => 'Successful', 'responseCode' => '0000', 'data' => ['status' => 'Paid', 'transactionId' => 'tx-9']])]);

    expect(app(ReconcilePayment::class)->handle($payment))->toBe('paid')
        ->and($payment->fresh())->status->toBe(PaymentStatus::Success)->provider_transaction_id->toBe('tx-9');
});

it('only fails an unpaid payment once the rider has had her time', function () {
    $payment = startHubtelPayment();
    Http::fake(['api-txnstatus.hubtel.com/*' => Http::response(['responseCode' => '0000', 'data' => ['status' => 'Unpaid']])]);

    expect(app(ReconcilePayment::class)->handle($payment))->toBe('waiting');

    $this->travel(16)->minutes();

    expect(app(ReconcilePayment::class)->handle($payment))->toBe('failed')
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Failed);
});

it('never fails a payment because Hubtel could not answer, and stops asking after a 403', function () {
    $payment = startHubtelPayment();
    Http::fake(['api-txnstatus.hubtel.com/*' => Http::response('Forbidden', 403)]);
    $this->travel(60)->minutes();
    $sentBefore = count(Http::recorded());

    expect(app(ReconcilePayment::class)->handle($payment))->toBe('unknown')
        ->and(Cache::has(HubtelGateway::STATUS_BLOCKED_KEY))->toBeTrue()
        ->and(app(ReconcilePayment::class)->handle($payment))->toBe('unknown')
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Initiated);

    Http::assertSentCount($sentBefore + 1); // one status check. The second never left.
});

it('sweeps waiting payments with payments:reconcile', function () {
    $payment = startHubtelPayment();
    Http::fake(['api-txnstatus.hubtel.com/*' => Http::response(['data' => ['status' => 'Paid']])]);

    $this->artisan('payments:reconcile')->expectsOutputToContain('Nothing waiting')->assertSuccessful();

    $this->travel(3)->minutes();
    $this->artisan('payments:reconcile')->expectsOutputToContain('1 paid')->assertSuccessful();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Success);
});

it('lets an operator settle by hand, with a note', function () {
    $payment = startHubtelPayment();

    expect(fn () => app(ResolveManually::class)->handle($payment, true, 'Esi', ' '))->toThrow(DomainException::class);

    app(ResolveManually::class)->handle($payment, true, 'Esi', 'Seen on the Hubtel dashboard');

    expect($payment->fresh())->status->toBe(PaymentStatus::Success)
        ->and($payment->fresh()->meta['manual']['by'])->toBe('Esi');

    expect(fn () => app(ResolveManually::class)->handle($payment->fresh(), false, 'Esi', 'x'))->toThrow(DomainException::class, 'already paid');
});

it('gives the console a payments screen, where only a trusted role may settle by hand', function () {
    syncAccess();
    Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel('admin'));
    $payment = startHubtelPayment();
    Http::fake(['api-txnstatus.hubtel.com/*' => Http::response(['data' => ['status' => 'Unpaid']])]);

    $this->actingAs(staff('operator'), 'platform');
    $this->get('/admin/payments')->assertOk()->assertSee('Aveo 1 month plan');

    Livewire\Livewire::test(ManagePayments::class)
        ->assertTableActionHidden('resolve', $payment)
        ->callTableAction('check', $payment);
    expect($payment->fresh()->status)->toBe(PaymentStatus::Initiated);

    $this->actingAs(staff(Staff::SUPER_ADMIN), 'platform');
    Livewire\Livewire::test(ManagePayments::class)
        ->callTableAction('resolve', $payment, ['outcome' => 'paid', 'note' => 'Seen on the dashboard'])
        ->assertHasNoTableActionErrors();

    expect($payment->fresh())->status->toBe(PaymentStatus::Success)
        ->and($payment->fresh()->meta['manual']['by'])->toBe('Test Staff');
});
