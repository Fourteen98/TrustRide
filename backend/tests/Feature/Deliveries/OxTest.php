<?php

use App\Support\Contracts\SmsSender;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Modules\Access\Models\Account;
use Modules\Access\Models\Staff;
use Modules\Deliveries\Actions\BuildRun;
use Modules\Deliveries\Actions\CancelDelivery;
use Modules\Deliveries\Actions\ConfirmDelivery;
use Modules\Deliveries\Actions\ConfirmPickup;
use Modules\Deliveries\Actions\CreateDelivery;
use Modules\Deliveries\Actions\FinishRun;
use Modules\Deliveries\Actions\ImportDeliveries;
use Modules\Deliveries\Actions\IssueInvoice;
use Modules\Deliveries\Actions\MarkInvoicePaid;
use Modules\Deliveries\Actions\SaveBusiness;
use Modules\Deliveries\Actions\StartRun;
use Modules\Deliveries\Enums\DeliveryStatus;
use Modules\Deliveries\Filament\Resources\BusinessResource\Pages\ManageBusinesses;
use Modules\Deliveries\Filament\Resources\DeliveryRunResource\Pages\ManageDeliveryRuns;
use Modules\Deliveries\Models\Business;
use Modules\Deliveries\Models\Delivery;
use Modules\Deliveries\Models\DeliveryPlan;
use Modules\Deliveries\Models\DeliveryRun;
use Modules\Deliveries\Models\Zone;
use Modules\Drivers\Actions\RegisterDriver;
use Modules\Drivers\Enums\DriverStatus;
use Modules\Drivers\Models\Driver;
use Modules\Messaging\Models\Message;

/*
 * Aveo Ox: a business sends parcels, they are batched by zone, one driver collects and delivers,
 * the customer gives a code at the door, and the business pays for its month.
 */

beforeEach(function () {
    syncAccess();
    Carbon::setTestNow('2026-11-09 10:00:00'); // a Monday morning, before the 14:00 cut-off

    $this->sms = oxSms();
    $this->operator = staff('operator');
    $this->lapaz = Zone::create(['name' => 'Lapaz', 'covers' => 'Lapaz, Abeka', 'cutoff_time' => '14:00', 'promise' => 'by 6pm']);
    $this->abokobi = Zone::create(['name' => 'Abokobi', 'cutoff_time' => '14:00', 'promise' => 'by 7pm']);
    $this->growth = DeliveryPlan::create(['name' => 'Growth', 'monthly_fee' => 90000, 'delivery_allowance' => 100, 'overage_fee' => 1000]);
    $this->starter = DeliveryPlan::create(['name' => 'Starter', 'monthly_fee' => 30000, 'delivery_allowance' => 2, 'at_limit' => 'stop']);

    $this->shop = app(SaveBusiness::class)->handle(null, [
        'name' => 'Ama\'s Closet', 'contact_name' => 'Ama', 'phone' => '0241112222', 'pickup_address' => 'Kaneshie Market, stall 14',
        'delivery_plan_id' => $this->growth->id,
    ]);

    $this->driver = app(RegisterDriver::class)->handle(['name' => 'Kojo Antwi', 'phone' => '0559998888']);
    Driver::whereKey($this->driver->id)->update(['status' => DriverStatus::Approved, 'work_types' => json_encode(['ox'])]);
    $this->driver = $this->driver->fresh();
});

/** Keeps every SMS that must always go out, so the test can read the customer's code. */
function oxSms(): object
{
    $sms = new class implements SmsSender
    {
        public array $sent = [];

        public function send(string $phone, string $message): void
        {
            $this->sent[] = compact('phone', 'message');
        }
    };

    app()->instance(SmsSender::class, $sms);

    return $sms;
}

/** The next request comes from this account's phone. */
function oxPhone(Account $account)
{
    app('auth')->forgetGuards();

    return test()->withToken($account->createToken('t')->plainTextToken);
}

function parcel(Business $business, array $overrides = []): Delivery
{
    return app(CreateDelivery::class)->handle($business, [
        'zone_id' => test()->lapaz->id, 'customer_name' => 'Esi Mensah', 'customer_phone' => '0201234567',
        'address' => 'House 12, Lapaz', 'landmark' => 'Behind the Total station', 'reference' => 'A-101', ...$overrides,
    ], test()->operator);
}

it('copies the plan onto the business, so a later change to the plan moves nobody', function () {
    expect($this->shop->monthly_fee)->toBe(90000)->and($this->shop->delivery_allowance)->toBe(100)->and($this->shop->phone)->toBe('+233241112222');

    $this->growth->update(['monthly_fee' => 120000]);
    expect($this->shop->fresh()->monthly_fee)->toBe(90000);
});

it('puts a parcel on today\'s run before the cut-off and tomorrow\'s after, and tells the customer', function () {
    $today = parcel($this->shop);
    $this->travelTo(Carbon::parse('2026-11-09 15:30'));
    $tomorrow = parcel($this->shop, ['customer_phone' => '0207654321']);

    expect($today->delivery_date->toDateString())->toBe('2026-11-09')
        ->and($tomorrow->delivery_date->toDateString())->toBe('2026-11-10')
        ->and(Message::query()->where('recipient_phone', '+233201234567')->value('body'))
        ->toBe("Ama's Closet is sending your order with Aveo Ox. It should reach you today by 6pm. Follow it: ".$today->trackingUrl());
});

it('stops a business at its limit only if its plan says so', function () {
    $small = app(SaveBusiness::class)->handle(null, ['name' => 'Abokobi Pharmacy', 'contact_name' => 'Kofi', 'phone' => '0242223333', 'pickup_address' => 'Madina', 'delivery_plan_id' => $this->starter->id]);
    parcel($small);
    parcel($small);

    expect(fn () => parcel($small))->toThrow(DomainException::class, 'has used 2 of its 2 deliveries for November');

    // A cancelled parcel gives its place back.
    app(CancelDelivery::class)->handle(Delivery::query()->where('business_id', $small->id)->first());
    expect(parcel($small)->exists)->toBeTrue();
});

it('uploads a file all or nothing, naming the wrong rows', function () {
    $bad = "customer_name,customer_phone,zone,address,landmark\nEsi,0201234567,Lapaz,House 1,\nAma,12345,Lapaz,House 2,\nKofi,0241234567,Kumasi,House 3,";

    expect(fn () => app(ImportDeliveries::class)->handle($this->shop, $bad))->toThrow(DomainException::class, 'Row 3: 12345 is not a Ghana phone number. Row 4: no zone called "Kumasi".')
        ->and(Delivery::count())->toBe(0);

    $good = "Customer Name,Customer Phone,Zone,Address,Reference\nEsi,0201234567,lapaz,House 1,A-1\nAma,0241234567,Abokobi,House 2,A-2\n";
    expect(app(ImportDeliveries::class)->handle($this->shop, $good))->toBe(2)
        ->and(Delivery::query()->pluck('zone_id')->all())->toBe([$this->lapaz->id, $this->abokobi->id]);
});

it('batches a zone\'s parcels onto one run, grouped by business, for a driver who does Ox', function () {
    $other = app(SaveBusiness::class)->handle(null, ['name' => 'Glow Cosmetics', 'contact_name' => 'Efua', 'phone' => '0243334444', 'pickup_address' => 'Osu', 'delivery_plan_id' => $this->growth->id]);
    $a = parcel($this->shop);
    $b = parcel($other, ['customer_phone' => '0207777777']);
    $c = parcel($this->shop, ['customer_phone' => '0208888888']);
    parcel($this->shop, ['zone_id' => $this->abokobi->id]);

    $rideOnly = app(RegisterDriver::class)->handle(['name' => 'Kwame', 'phone' => '0241231234']);
    Driver::whereKey($rideOnly->id)->update(['status' => DriverStatus::Approved]);
    expect(fn () => app(BuildRun::class)->handle($this->lapaz, today(), $rideOnly->fresh()))->toThrow(DomainException::class, 'doesn\'t do Ox work');

    $run = app(BuildRun::class)->handle($this->lapaz, today(), $this->driver, $this->operator);

    expect($run->deliveries->pluck('id')->all())->toBe([$a->id, $c->id, $b->id])
        ->and($run->deliveries->pluck('status')->unique()->all())->toBe([DeliveryStatus::Assigned])
        ->and($run->driver_pay)->toBe((int) config('deliveries.default_run_pay'))
        ->and(fn () => app(BuildRun::class)->handle($this->lapaz, today(), $this->driver))->toThrow(DomainException::class, 'No parcels are waiting for Lapaz');
});

it('runs a delivery end to end from the driver\'s app: collect, code at the door, not delivered, finish', function () {
    $esi = parcel($this->shop);
    $kofi = parcel($this->shop, ['customer_name' => 'Kofi Boateng', 'customer_phone' => '0241234567']);
    $run = app(BuildRun::class)->handle($this->lapaz, today(), $this->driver);
    $account = $this->driver->account;

    $sheet = oxPhone($account)->getJson('/api/driver/v1/parcels')->assertOk()->json('runs.0');
    expect($sheet['pickups'][0])->toMatchArray(['name' => 'Ama\'s Closet', 'parcels' => 2, 'collected' => false])
        ->and(array_column($sheet['drops'], 'customer_name'))->toBe(['Esi Mensah', 'Kofi Boateng']);

    oxPhone($account)->postJson('/api/driver/v1/parcels/collect', ['run_id' => $run->id, 'business_id' => $this->shop->id])
        ->assertStatus(422)->assertJsonPath('message', 'Start the run first.');
    oxPhone($account)->postJson('/api/driver/v1/parcels/start', ['run_id' => $run->id])->assertOk();
    oxPhone($account)->postJson('/api/driver/v1/parcels/collect', ['run_id' => $run->id, 'business_id' => $this->shop->id])
        ->assertOk()->assertJsonPath('run.counts.with_driver', 2);

    // Each customer gets her own code, straight to her phone. It is nowhere in the message log.
    $text = collect($this->sms->sent)->firstWhere('phone', '+233201234567')['message'];
    preg_match('/code (\d{4})/', $text, $code);
    expect($text)->toStartWith('Ama\'s Closet: your order is on its way with Aveo Ox, today by 6pm.')
        ->and(Message::query()->where('body', 'like', "%{$code[1]}%")->exists())->toBeFalse();

    oxPhone($account)->postJson('/api/driver/v1/parcels/deliver', ['delivery_id' => $esi->id, 'code' => $code[1] === '0000' ? '1111' : '0000'])
        ->assertStatus(422)->assertJsonPath('message', 'That code is wrong. 4 tries left.');
    oxPhone($account)->postJson('/api/driver/v1/parcels/deliver', ['delivery_id' => $esi->id, 'code' => $code[1]])
        ->assertOk()->assertJsonPath('run.counts.delivered', 1);

    oxPhone($account)->postJson('/api/driver/v1/parcels/finish', ['run_id' => $run->id])
        ->assertStatus(422)->assertJsonPath('message', '1 parcel is still with you. Mark each delivered or couldn\'t deliver.');
    oxPhone($account)->postJson('/api/driver/v1/parcels/not-delivered', ['delivery_id' => $kofi->id, 'reason' => 'Nobody home, phone off'])->assertOk();
    oxPhone($account)->postJson('/api/driver/v1/parcels/finish', ['run_id' => $run->id])->assertOk()->assertJsonPath('run.status', 'done');

    expect(Message::query()->where('recipient_phone', '+233241234567')->latest('id')->value('body'))
        ->toBe("We couldn't deliver your Ama's Closet order today: Nobody home, phone off. Aveo will call you to arrange another day.");
});

it('locks the code after too many wrong tries, and lets staff confirm with a reason', function () {
    $esi = parcel($this->shop);
    $run = app(BuildRun::class)->handle($this->lapaz, today(), $this->driver);
    app(StartRun::class)->handle($run);
    app(ConfirmPickup::class)->handle($run, $this->shop);

    foreach (range(1, 5) as $try) {
        try {
            app(ConfirmDelivery::class)->handle($esi->fresh(), 'nope');
        } catch (DomainException) {
        }
    }

    expect(fn () => app(ConfirmDelivery::class)->handle($esi->fresh(), 'nope'))->toThrow(DomainException::class, 'Too many wrong codes')
        ->and(fn () => app(ConfirmDelivery::class)->handle($esi->fresh(), null, $this->operator, ''))->toThrow(DomainException::class, 'Say how you know');

    app(ConfirmDelivery::class)->handle($esi->fresh(), null, $this->operator, 'She called to say she has it');
    expect($esi->fresh()->status)->toBe(DeliveryStatus::Delivered)
        ->and($esi->fresh()->delivered_note)->toContain('without the code: She called to say she has it');
});

it('sends parcels never collected back to the queue for the next day', function () {
    $other = app(SaveBusiness::class)->handle(null, ['name' => 'Glow Cosmetics', 'contact_name' => 'Efua', 'phone' => '0243334444', 'pickup_address' => 'Osu', 'delivery_plan_id' => $this->growth->id]);
    parcel($this->shop);
    $missed = parcel($other, ['customer_phone' => '0207777777']);
    $run = app(BuildRun::class)->handle($this->lapaz, today(), $this->driver);
    app(StartRun::class)->handle($run);

    expect(app(FinishRun::class)->handle($run))->toBe(2);
    expect($missed->fresh()->status)->toBe(DeliveryStatus::Pending)
        ->and($missed->fresh()->delivery_date->toDateString())->toBe('2026-11-10')
        ->and($missed->fresh()->delivery_run_id)->toBeNull();
});

it('only shows a driver his own runs', function () {
    parcel($this->shop);
    $run = app(BuildRun::class)->handle($this->lapaz, today(), $this->driver);

    $someone = app(RegisterDriver::class)->handle(['name' => 'Yaw', 'phone' => '0244445555']);
    oxPhone($someone->account)->getJson('/api/driver/v1/parcels')->assertOk()->assertJsonPath('runs', []);
    oxPhone($someone->account)->postJson('/api/driver/v1/parcels/start', ['run_id' => $run->id])->assertNotFound();
});

it('bills the month: plan fee, each delivery over the allowance, and the live map', function () {
    $this->shop->update(['delivery_allowance' => 2, 'live_map' => true]);
    foreach (['0201111111', '0202222222', '0203333333', '0204444444'] as $phone) {
        parcel($this->shop, ['customer_phone' => $phone]);
    }
    app(CancelDelivery::class)->handle(Delivery::first());

    $invoice = app(IssueInvoice::class)->handle($this->shop, now());
    expect($invoice->deliveries_count)->toBe(3)
        ->and($invoice->overage_count)->toBe(1)
        ->and($invoice->overage_amount)->toBe(1000)
        ->and($invoice->total)->toBe(90000 + 1000 + (int) config('deliveries.live_map_fee'));

    app(MarkInvoicePaid::class)->handle($invoice, $this->operator, 'MoMo 1234');
    expect(fn () => app(IssueInvoice::class)->handle($this->shop, now()))->toThrow(DomainException::class, 'is paid');
});

it('gives the customer a page with no phone, address or code on it', function () {
    $esi = parcel($this->shop);

    $this->get("/o/{$esi->public_id}")->assertOk()
        ->assertSee('Hello Esi')
        ->assertSee('Expected today by 6pm')
        ->assertDontSee('0201234567')->assertDontSee('+233201234567')
        ->assertDontSee('House 12');
    $this->get('/o/not-a-real-one')->assertNotFound();
});

it('removes customer details once the parcel is long done with', function () {
    $esi = parcel($this->shop);
    $esi->update(['status' => DeliveryStatus::Delivered, 'delivered_at' => now()]);

    $this->travel(config('deliveries.customer_data_days') + 1)->days();
    $this->artisan('ox:prune-customers')->assertSuccessful();

    expect($esi->fresh()->customer_phone)->toBe('')->and($esi->fresh()->address)->toBe('Removed')
        ->and($esi->fresh()->business_id)->toBe($this->shop->id);
});

it('shows the console screens to an operator, who can build a run there', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    parcel($this->shop);
    $this->actingAs($this->operator, 'platform');

    Livewire::test(ManageDeliveryRuns::class)
        ->callAction('build', ['date' => today()->toDateString(), 'zone_id' => $this->lapaz->id, 'driver_id' => $this->driver->id, 'driver_pay' => 80])
        ->assertHasNoActionErrors();
    expect(DeliveryRun::first()->driver_pay)->toBe(8000);

    // An operator sends parcels but doesn't set up businesses.
    Livewire::test(ManageBusinesses::class)->assertActionHidden('create');

    foreach (['deliveries', 'delivery-runs', 'businesses', 'zones', 'delivery-plans'] as $screen) {
        $this->get("/admin/{$screen}")->assertOk();
    }

    // Billing is not an operator's by default.
    $this->get('/admin/business-invoices')->assertForbidden();
});

it('shows invoices to a super admin', function () {
    $this->actingAs(staff(Staff::SUPER_ADMIN), 'platform')->get('/admin/business-invoices')->assertOk();
});
