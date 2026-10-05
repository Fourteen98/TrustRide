<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Modules\Access\Models\Account;
use Modules\Messaging\Models\Message;
use Modules\Payments\Actions\ApplyOutcome;
use Modules\Payments\Enums\PaymentStatus;
use Modules\Payments\Jobs\SettleFakePayment;
use Modules\Payments\Models\Payment;
use Modules\Riders\Actions\EraseRider;
use Modules\Riders\Actions\RequestDataDeletion;
use Modules\Riders\Actions\SubmitApplication;
use Modules\Riders\Models\Rider;
use Modules\Subscriptions\Actions\ApproveApplicationWithSeats;
use Modules\Subscriptions\Actions\ReleaseSeat;
use Modules\Subscriptions\Actions\ReserveSeat;
use Modules\Subscriptions\Actions\SettlePlanPurchase;
use Modules\Subscriptions\Enums\PurchaseStatus;
use Modules\Subscriptions\Enums\SeatStatus;
use Modules\Subscriptions\Filament\Resources\PlanPurchaseResource\Pages\ManagePlanPurchases;
use Modules\Subscriptions\Models\Plan;
use Modules\Subscriptions\Models\PlanPurchase;
use Modules\Subscriptions\Models\Seat;
use Modules\Subscriptions\Models\Subscription;

/*
 * Buying a plan in the app, end to end, on the fake gateway. The settle job is held back with
 * Queue::fake so each test decides when "the rider approves the prompt".
 */
beforeEach(function () {
    syncAccess();
    Carbon::setTestNow('2026-10-05 09:00:00'); // a Monday
    config(['payments.driver' => 'fake']);

    $this->network = network();
    $this->run = run($this->network);
    $this->account = Account::create(['phone' => '+233241234567'])->assignRole('rider');
    $this->withToken($this->account->createToken('test')->plainTextToken);
    $this->plan = Plan::query()->where('duration_days', 30)->firstOrFail();
});

function riderWithSeat(): Rider
{
    $application = app(SubmitApplication::class)->handle(test()->account, [
        'name' => 'Ama Owusu', 'route_id' => test()->network['route']->id,
        'stop_id' => test()->network['stops'][1]->id, 'morning_time' => '06:30',
    ]);
    app(ApproveApplicationWithSeats::class)->handle($application, [test()->run->id], staff('operator'));

    return $application->rider;
}

function buy(array $overrides = [])
{
    return test()->postJson('/api/rider/v1/purchase', ['plan_id' => test()->plan->id, 'network' => 'mtn', ...$overrides]);
}

/** Stands in for the rider approving (or the gateway failing) the prompt. */
function settle(bool $paid = true): void
{
    app(ApplyOutcome::class)->handle(Payment::query()->latest('id')->firstOrFail(), $paid, 'FAKE-1', 'fake', null, $paid ? null : 'Declined');
}

it('lists the networks she can pay from next to the plans', function () {
    $this->getJson('/api/rider/v1/plans')->assertOk()
        ->assertJsonPath('networks.0', ['key' => 'mtn', 'label' => 'MTN MoMo', 'automatic' => true])->assertJsonPath('networks.2.automatic', false)
        ->assertJsonCount(3, 'networks');
});

it('prompts her phone, then activates her subscription and sends the receipt when the money arrives', function () {
    Queue::fake([SettleFakePayment::class]);
    riderWithSeat();

    buy()->assertCreated()
        ->assertJsonPath('purchase.status', 'pending')
        ->assertJsonPath('purchase.amount_label', 'GHS 60')
        ->assertJsonPath('purchase.network', 'MTN MoMo');

    Queue::assertPushed(SettleFakePayment::class);
    $payment = Payment::query()->sole();
    expect($payment)->phone->toBe('+233241234567')->amount->toBe(6000)->payable_type->toBe('plan_purchase')
        ->and(Subscription::count())->toBe(0);

    $this->getJson('/api/rider/v1/home')->assertJsonPath('purchase.status', 'pending')->assertJsonPath('subscription', null);

    settle();

    $this->getJson('/api/rider/v1/purchase')->assertOk()->assertJsonPath('purchase.status', 'activated');
    $this->getJson('/api/rider/v1/home')
        ->assertJsonPath('purchase', null)
        ->assertJsonPath('subscription.plan', $this->plan->name)
        ->assertJsonPath('seats.0.status', 'active');

    expect(PlanPurchase::query()->sole())->subscription_id->toBe(Subscription::query()->sole()->id)
        ->and(Subscription::query()->sole()->activated_by)->toBeNull()
        ->and(Message::query()->where('body', 'like', 'You\'re in, Ama Owusu%')->count())->toBe(1);
});

it('really settles by itself through the fake gateway job', function () {
    riderWithSeat();

    buy()->assertCreated(); // the test queue runs the job at once

    expect(PlanPurchase::query()->sole()->status)->toBe(PurchaseStatus::Activated);
});

it('can charge a different wallet from the one she logs in with', function () {
    Queue::fake([SettleFakePayment::class]);
    riderWithSeat();

    buy(['phone' => '0201112222', 'network' => 'telecel'])->assertCreated();

    expect(Payment::query()->sole())->phone->toBe('+233201112222')->network->toBe('telecel');

    buy(['phone' => '12345'])->assertStatus(422);
});

it('tells her when the payment fails, and lets her try again', function () {
    Queue::fake([SettleFakePayment::class]);
    riderWithSeat();

    buy()->assertCreated();
    settle(false);

    $this->getJson('/api/rider/v1/home')->assertJsonPath('purchase.status', 'failed');
    expect(Subscription::count())->toBe(0)
        ->and(Seat::query()->sole()->status)->toBe(SeatStatus::Reserved)
        ->and(Message::query()->where('body', 'like', '%did not go through%')->count())->toBe(1);

    buy()->assertCreated();
    settle();

    expect(Subscription::count())->toBe(1);
});

it('refuses before any money is asked for when she could not be activated', function () {
    Queue::fake([SettleFakePayment::class]);

    buy()->assertStatus(422)->assertJsonPath('message', 'Apply for a seat first.');

    riderWithSeat();
    buy(['network' => 'glo'])->assertStatus(422);
    buy(['plan_id' => 9999])->assertStatus(422)->assertJsonPath('message', 'That plan is no longer sold.');

    expect(Payment::count())->toBe(0);
});

it('allows one prompt at a time, and one renewal ahead', function () {
    Queue::fake([SettleFakePayment::class]);
    riderWithSeat();

    buy()->assertCreated();
    buy()->assertStatus(422)->assertJsonPath('message', fn ($message) => str_contains($message, 'already waiting'));
    settle();

    buy()->assertCreated(); // a renewal, while the first is running
    settle();

    $subscriptions = Subscription::query()->orderBy('starts_on')->get();
    expect($subscriptions)->toHaveCount(2)
        ->and($subscriptions[1]->starts_on->toDateString())->toBe($subscriptions[0]->ends_on->addDay()->toDateString());

    buy()->assertStatus(422)->assertJsonPath('message', 'You have already paid for your next subscription.');
});

it('flags a purchase she paid for that cannot be activated, and lets an operator finish it', function () {
    Queue::fake([SettleFakePayment::class]);
    $rider = riderWithSeat();

    buy()->assertCreated();
    app(ReleaseSeat::class)->handle(Seat::query()->sole()); // her seat goes while she is paying
    settle();

    $purchase = PlanPurchase::query()->sole();
    expect($purchase)->status->toBe(PurchaseStatus::NeedsAttention)->problem->toContain('no seat')
        ->and(Payment::query()->sole()->status)->toBe(PaymentStatus::Success)
        ->and(Subscription::count())->toBe(0);

    $this->getJson('/api/rider/v1/home')->assertJsonPath('purchase.status', 'needs_attention');

    // The operator sees it flagged, gives her a seat again, then tries again from the console.
    Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel('admin'));
    $operator = staff('operator');
    $this->actingAs($operator, 'platform');
    $this->get('/admin/purchases')->assertOk()->assertSee('Ama Owusu');

    $page = ManagePlanPurchases::class;
    Livewire\Livewire::test($page)->callTableAction('activate', $purchase);
    expect($purchase->fresh()->status)->toBe(PurchaseStatus::NeedsAttention); // still no seat

    app(ReserveSeat::class)->handle($rider, $this->run, $this->network['stops'][1]->id);
    Livewire\Livewire::test($page)->callTableAction('activate', $purchase);

    expect($purchase->fresh()->status)->toBe(PurchaseStatus::Activated)
        ->and(Subscription::query()->sole()->activated_by)->toBe($operator->id);

    // Asking twice gives her nothing more.
    app(SettlePlanPurchase::class)->paid($purchase->fresh());
    expect(Subscription::count())->toBe(1);
});

it('still activates when the money arrives after the payment was marked failed', function () {
    Queue::fake([SettleFakePayment::class]);
    riderWithSeat();

    buy()->assertCreated();
    settle(false);
    settle(true);

    expect(PlanPurchase::query()->sole()->status)->toBe(PurchaseStatus::Activated)
        ->and(Subscription::count())->toBe(1);
});

it('removes her phone number from her payments when her data is erased', function () {
    Queue::fake([SettleFakePayment::class]);
    $rider = riderWithSeat();
    buy()->assertCreated();
    settle();

    $request = app(RequestDataDeletion::class)->handle($rider);
    app(EraseRider::class)->handle($request, staff('super-admin'));

    expect(Payment::query()->sole())->phone->toBeNull()->meta->toBeNull()->amount->toBe(6000);
});
