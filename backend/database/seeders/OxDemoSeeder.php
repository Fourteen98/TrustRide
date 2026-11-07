<?php

namespace Database\Seeders;

use App\Support\Contracts\SmsSender;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Carbon;
use Modules\Access\Models\Staff;
use Modules\Deliveries\Actions\BuildRun;
use Modules\Deliveries\Actions\CancelDelivery;
use Modules\Deliveries\Actions\ConfirmDelivery;
use Modules\Deliveries\Actions\ConfirmPickup;
use Modules\Deliveries\Actions\CreateDelivery;
use Modules\Deliveries\Actions\FailDelivery;
use Modules\Deliveries\Actions\FinishRun;
use Modules\Deliveries\Actions\IssueInvoice;
use Modules\Deliveries\Actions\MarkInvoicePaid;
use Modules\Deliveries\Actions\SaveBusiness;
use Modules\Deliveries\Actions\StartRun;
use Modules\Deliveries\Enums\DeliveryStatus;
use Modules\Deliveries\Models\Business;
use Modules\Deliveries\Models\Delivery;
use Modules\Deliveries\Models\DeliveryPlan;
use Modules\Deliveries\Models\DeliveryRun;
use Modules\Deliveries\Models\Zone;
use Modules\Drivers\Actions\ApproveDriver;
use Modules\Drivers\Actions\RecordVettingCheck;
use Modules\Drivers\Actions\RegisterDriver;
use Modules\Drivers\Enums\VettingResult;
use Modules\Drivers\Enums\WorkType;
use Modules\Drivers\Models\Driver;
use Modules\Drivers\Models\VettingItem;

/**
 * Aveo Ox in the demo: the zones, plans and three businesses from the brief, three weeks of parcels through the
 * real Actions, and today built around the moment it runs: one run out on the road with a business collected and
 * a parcel delivered, and parcels waiting in other zones for an operator to batch.
 *
 * Called by DemoSeeder after the Ride demo. The customer codes are caught on their way out, so the replay can
 * type them at the door like a driver.
 */
class OxDemoSeeder
{
    /** Zone => [places in it, cut-off, promise]. From the brief: illustrative, editable in the console. */
    private const ZONES = [
        'Lapaz' => ['Lapaz, Abeka, Tesano', '14:00', 'by 6pm'],
        'Ablekuma' => ['Ablekuma, Fan Milk, Olebu', '14:00', 'by 6pm'],
        'Bortianor' => ['Bortianor, Tetegu, Weija', '13:00', 'by 6pm'],
        'Abokobi' => ['Abokobi, Pantang, Ashongman', '15:00', 'by 7pm'],
    ];

    /** Name, contact, phone, pickup, landmark, plan, zones its customers live in, parcels a day, live map. */
    private const BUSINESSES = [
        ['Ama\'s Closet', 'Ama Darko', '0245550301', 'Kaneshie Market, stall 14', 'Opposite the First Light bus stop', 'Growth', ['Lapaz', 'Ablekuma'], 3, false],
        ['Madina Care Pharmacy', 'Kofi Ansah', '0245550302', 'Madina Zongo Junction', 'Next to the GCB', 'Starter', ['Abokobi'], 1, false],
        ['Glow Cosmetics', 'Efua Badu', '0245550303', 'Oxford Street, Osu', 'Above the Vodafone shop', 'Custom', ['Bortianor', 'Ablekuma', 'Abokobi', 'Lapaz'], 5, true],
    ];

    private const DRIVERS = [['Kojo Antwi', '0245550201'], ['Kwesi Appiah', '0245550202']];

    private const CUSTOMERS = [
        'Akosua Mensah', 'Yaw Boateng', 'Abena Owusu', 'Kwabena Asare', 'Adwoa Frimpong', 'Kofi Agyeman', 'Esi Quaye', 'Nii Armah',
        'Afua Sarpong', 'Kwame Tetteh', 'Naa Lamptey', 'Kojo Addo', 'Ama Nyarko', 'Fiifi Eshun', 'Akua Danso', 'Selorm Adzo',
    ];

    private const LANDMARKS = ['Behind the Total station', 'Blue gate after the church', 'Near the Methodist school', 'Opposite the barbering shop', 'Last house before the football park'];

    private const NOT_DELIVERED = ['Nobody at home', 'Customer not answering the phone', 'Address could not be found'];

    private Staff $admin;

    /** @var array<string, Zone> */
    private array $zones = [];

    /** @var list<Driver> */
    private array $drivers = [];

    /** Catches every code texted during the replay, by delivery id. */
    private object $catcher;

    private int $customer = 0;

    public function run(Staff $admin, CarbonImmutable $now, int $days = 21): void
    {
        $this->admin = $admin;
        $fixed = Carbon::getTestNow();
        $this->catcher = $this->catchCodes();

        try {
            $this->at($now->subDays($days)->setTime(8, 0));
            $this->setUp();

            for ($ago = $days; $ago >= 1; $ago--) {
                $day = $now->subDays($ago)->startOfDay();

                if (! $day->isSunday()) {
                    $this->pastDay($day);
                }
            }

            $this->bill($now);
            $this->today($now);
        } finally {
            app()->forgetInstance(SmsSender::class);
            Carbon::setTestNow($fixed);
            CarbonImmutable::setTestNow($fixed);
        }
    }

    /** One line for the terminal. */
    public static function summary(): string
    {
        $today = Delivery::query()->whereDate('delivery_date', today())->count();
        $waiting = Delivery::query()->whereDate('delivery_date', today())->where('status', DeliveryStatus::Pending)->count();

        return sprintf('Ox: %d businesses, %d parcels over three weeks. Today %d parcels, %d waiting for a run.',
            Business::count(), Delivery::count(), $today, $waiting);
    }

    private function setUp(): void
    {
        foreach (self::ZONES as $name => [$covers, $cutoff, $promise]) {
            $this->zones[$name] = Zone::create(['name' => $name, 'covers' => $covers, 'cutoff_time' => $cutoff, 'promise' => $promise]);
        }

        $plans = collect([
            ['Starter', 30000, 30, 1200],
            ['Growth', 90000, 100, 1000],
            ['Business', 240000, 300, 900],
            ['Custom', 0, null, 0],
        ])->mapWithKeys(fn (array $plan) => [$plan[0] => DeliveryPlan::create([
            'name' => $plan[0], 'monthly_fee' => $plan[1], 'delivery_allowance' => $plan[2], 'overage_fee' => $plan[3],
        ])]);

        foreach (self::BUSINESSES as [$name, $contact, $phone, $address, $landmark, $plan, , , $map]) {
            app(SaveBusiness::class)->handle(null, [
                'name' => $name, 'contact_name' => $contact, 'phone' => $phone, 'pickup_address' => $address, 'pickup_landmark' => $landmark,
                'delivery_plan_id' => $plans[$plan]->id, 'live_map' => $map,
                // Glow Cosmetics agreed its own price: GHS 9,000 for about 900 deliveries.
                ...($plan === 'Custom' ? ['monthly_fee' => 900000, 'delivery_allowance' => 900, 'overage_fee' => 1000] : []),
            ]);
        }

        foreach (self::DRIVERS as [$name, $phone]) {
            $driver = app(RegisterDriver::class)->handle(['name' => $name, 'phone' => $phone, 'licence_number' => 'DEMO-OX-'.substr($phone, -3), 'monthly_commission' => 0]);
            VettingItem::query()->for($driver)->get()
                ->each(fn (VettingItem $item) => app(RecordVettingCheck::class)->handle($driver, $item, VettingResult::Passed, $this->admin));
            app(ApproveDriver::class)->handle($driver, $this->admin);
            $driver->update(['work_types' => [WorkType::Ox]]);
            $this->drivers[] = $driver->fresh();
        }
    }

    /** A finished day: parcels through the morning, a run per zone after the cut-off, delivered by evening. */
    private function pastDay(CarbonImmutable $day): void
    {
        $this->parcels($day, fn (int $index) => $day->setTime(8, 30)->addMinutes(40 * $index));

        foreach (array_values($this->zones) as $index => $zone) {
            $this->at($day->setTime((int) substr($zone->cutoff_time, 0, 2), 10));

            try {
                $run = app(BuildRun::class)->handle($zone, $day, $this->drivers[$index % 2], $this->admin);
            } catch (DomainException) {
                continue; // nothing for this zone today
            }

            $this->drive($run, $day->setTime(15, 0), everything: true);
        }

        // Now and then a business takes one back before it is collected.
        if ($day->day % 6 === 0 && $parcel = Delivery::query()->where('status', DeliveryStatus::Pending)->first()) {
            app(CancelDelivery::class)->handle($parcel);
        }
    }

    /** Today, around this moment. */
    private function today(CarbonImmutable $now): void
    {
        $morning = $now->startOfDay()->setTime(8, 0);
        // Orders count as in before their zone's cut-off, so they are for today whenever the demo is made.
        $this->parcels($now->startOfDay(), fn (int $index) => $morning->addMinutes(25 * $index), $now);

        // Lapaz is out on the road now: Ama's Closet collected and one delivered, Glow Cosmetics still to collect.
        $this->at($now->subMinutes(50));
        $run = app(BuildRun::class)->handle($this->zones['Lapaz'], $now, $this->drivers[0], $this->admin);
        app(StartRun::class)->handle($run);
        $closet = Business::query()->where('name', 'Ama\'s Closet')->first();

        if ($run->deliveries()->where('business_id', $closet->id)->exists()) {
            $this->at($now->subMinutes(35));
            app(ConfirmPickup::class)->handle($run, $closet);
            $this->at($now->subMinutes(10));
            $first = $run->deliveries()->where('status', DeliveryStatus::PickedUp)->first();
            app(ConfirmDelivery::class)->handle($first, $this->catcher->codes[$first->id]);
        }

        $this->at($now);
    }

    /** Each business sends its parcels for the day, to customers in its zones. */
    private function parcels(CarbonImmutable $day, callable $time, ?CarbonImmutable $notAfter = null): void
    {
        $sent = 0;

        foreach (self::BUSINESSES as [$name, , , , , , $zones, $perDay]) {
            $business = Business::query()->where('name', $name)->first();
            // A little more on Fridays and Saturdays, a little less on Mondays.
            $count = max(1, $perDay + ($day->isFriday() || $day->isSaturday() ? 1 : 0) - ($day->isMonday() ? 1 : 0));

            for ($i = 0; $i < $count; $i++) {
                $at = $time($sent++);
                $this->at($notAfter && $at->gt($notAfter) ? $notAfter : $at);
                $zone = $this->zones[$zones[($day->day + $i) % count($zones)]];
                $who = self::CUSTOMERS[$this->customer % count(self::CUSTOMERS)];
                $this->customer++;

                try {
                    app(CreateDelivery::class)->handle($business, [
                        'zone_id' => $zone->id,
                        'customer_name' => $who,
                        'customer_phone' => '020'.str_pad((string) (5550000 + $this->customer % 400), 7, '0', STR_PAD_LEFT),
                        'address' => 'House '.(3 + $this->customer % 40).', '.explode(', ', $zone->covers)[$this->customer % 3],
                        'landmark' => self::LANDMARKS[$this->customer % count(self::LANDMARKS)],
                        'reference' => strtoupper(substr($name, 0, 2)).'-'.(1000 + $this->customer),
                    ], $this->admin, $day->setTime(9, 0));
                } catch (DomainException) {
                    // A plan that stops at its limit refuses, as it would in real life.
                }
            }
        }
    }

    /** A driver works a run: collects at each business, then each door. One in about twelve can't be delivered. */
    private function drive(DeliveryRun $run, CarbonImmutable $from, bool $everything): void
    {
        $this->at($from);
        app(StartRun::class)->handle($run);
        $minute = 0;

        foreach ($run->deliveries()->get()->pluck('business_id')->unique() as $businessId) {
            $this->at($from->addMinutes($minute += 20));
            app(ConfirmPickup::class)->handle($run, Business::find($businessId));
        }

        foreach ($run->deliveries()->get() as $parcel) {
            $this->at($from->addMinutes($minute += 18));

            if ($parcel->id % 12 === 5) {
                app(FailDelivery::class)->handle($parcel, self::NOT_DELIVERED[$parcel->id % 3]);
            } else {
                app(ConfirmDelivery::class)->handle($parcel, $this->catcher->codes[$parcel->id]);
            }
        }

        if ($everything) {
            $this->at($from->addMinutes($minute + 15));
            app(FinishRun::class)->handle($run);
        }
    }

    /** Last month's invoices: issued, and two of three paid. */
    private function bill(CarbonImmutable $now): void
    {
        $this->at($now->startOfMonth()->setTime(9, 0));
        $last = $now->subMonthNoOverflow()->startOfMonth();

        Business::query()->orderBy('id')->get()->each(function (Business $business, int $index) use ($last) {
            $invoice = app(IssueInvoice::class)->handle($business, $last);

            if ($index < 2) {
                app(MarkInvoicePaid::class)->handle($invoice, $this->admin, 'MoMo '.(40210 + $index));
            }
        });
    }

    /** Stands in for the SMS sender, keeping each customer's code so the replay can type it at the door. */
    private function catchCodes(): object
    {
        $catcher = new class implements SmsSender
        {
            /** @var array<int, string> */
            public array $codes = [];

            public function send(string $phone, string $message): void
            {
                if (preg_match('/code (\d+) at the door\. Follow it: .*\/o\/(\w+)$/', $message, $match)
                    && $delivery = Delivery::query()->where('public_id', $match[2])->first()) {
                    $this->codes[$delivery->id] = $match[1];
                }
            }
        };

        app()->instance(SmsSender::class, $catcher);

        return $catcher;
    }

    private function at(CarbonImmutable $moment): void
    {
        Carbon::setTestNow($moment);
        CarbonImmutable::setTestNow($moment);
    }
}
