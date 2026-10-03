<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Access\Models\Account;
use Modules\Access\Models\Staff;
use Modules\Drivers\Actions\ApproveDriver;
use Modules\Drivers\Actions\ApproveVehicle;
use Modules\Drivers\Actions\RecordVettingCheck;
use Modules\Drivers\Actions\RegisterDriver;
use Modules\Drivers\Actions\RegisterMate;
use Modules\Drivers\Enums\VettingResult;
use Modules\Drivers\Models\Vehicle;
use Modules\Drivers\Models\VettingItem;
use Modules\Network\Actions\SetRouteStops;
use Modules\Network\Models\Route;
use Modules\Network\Models\Stop;
use Modules\Riders\Actions\SubmitApplication;
use Modules\Subscriptions\Actions\ActivateSubscription;
use Modules\Subscriptions\Actions\ApproveApplicationWithSeats;
use Modules\Subscriptions\Actions\SaveRun;
use Modules\Subscriptions\Models\Plan;
use RuntimeException;

/**
 * Made-up data so the console has something to show: one route, one driver with a run,
 * four riders with seats and two applications waiting.
 *
 *   php artisan db:seed --class=DemoSeeder
 *
 * It goes through the same Actions the console uses, so it also proves the path works.
 * It refuses to run in production. The route and names are examples, not the pilot.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('The demo seeder must not run in production.');
        }

        $this->call(DatabaseSeeder::class);

        if (Route::query()->exists()) {
            $this->command?->warn('There is already a route, so the demo data was not added again.');

            return;
        }

        $admin = Staff::query()->firstOrFail();

        $stops = collect([
            ['Barrier', 'By the toll booth', 0],
            ['Old Barrier', 'Opposite the filling station', 8],
            ['Mallam Junction', 'Under the footbridge', 20],
            ['Kaneshie', 'First Light', 35],
            ['Circle', 'Odo Rise', 50],
        ])->map(fn (array $stop) => [
            'stop' => Stop::query()->create(['name' => $stop[0], 'landmark' => $stop[1], 'area' => 'Demo']),
            'minutes' => $stop[2],
        ]);

        $route = Route::query()->create(['name' => 'Barrier to Circle (demo)', 'origin_area' => 'Bortianor–Barrier', 'destination' => 'Circle']);
        app(SetRouteStops::class)->handle($route, $stops->map(fn ($row) => ['stop_id' => $row['stop']->id, 'minutes_from_start' => $row['minutes']])->all());

        $driver = app(RegisterDriver::class)->handle([
            'name' => 'Kwame Mensah', 'phone' => '0240000001', 'licence_number' => 'DEMO-0001', 'monthly_commission' => 30000,
        ]);
        app(RegisterMate::class)->handle($driver, 'Kofi Asante', '0240000002');

        $vehicle = Vehicle::query()->create([
            'driver_id' => $driver->id, 'registration_number' => 'GT 1234-22', 'make' => 'Toyota Hiace', 'colour' => 'White', 'seats_total' => 15,
        ]);

        foreach ([$driver, $vehicle] as $subject) {
            VettingItem::query()->for($subject)->get()
                ->each(fn (VettingItem $item) => app(RecordVettingCheck::class)->handle($subject, $item, VettingResult::Passed, $admin));
        }

        app(ApproveDriver::class)->handle($driver, $admin);
        app(ApproveVehicle::class)->handle($vehicle);

        $run = app(SaveRun::class)->handle([
            'route_id' => $route->id, 'driver_id' => $driver->id, 'vehicle_id' => $vehicle->id, 'direction' => 'outbound',
            'departs_at' => '06:30', 'days_of_week' => [1, 2, 3, 4, 5, 6, 7], 'seats_held' => 12,
        ]);

        $month = Plan::query()->where('duration_days', 30)->firstOrFail();

        $riders = [
            ['Ama Owusu', '0240000011', 0, 'paid'],
            ['Esi Boateng', '0240000012', 1, 'paid'],
            ['Abena Darko', '0240000013', 2, 'paid'],
            ['Yaa Asantewaa', '0240000014', 2, 'approved'],
            ['Akosua Frimpong', '0240000015', 3, 'waiting'],
            ['Adwoa Sarpong', '0240000016', 0, 'waiting'],
        ];

        foreach ($riders as [$name, $phone, $stopIndex, $stage]) {
            $account = Account::query()->create(['phone' => '+233'.substr($phone, 1), 'name' => $name]);
            $account->assignRole('rider');

            $application = app(SubmitApplication::class)->handle($account, [
                'name' => $name, 'route_id' => $route->id, 'stop_id' => $stops[$stopIndex]['stop']->id, 'morning_time' => '06:30',
            ]);

            if ($stage === 'waiting') {
                continue;
            }

            app(ApproveApplicationWithSeats::class)->handle($application, [$run->id], $admin);

            if ($stage === 'paid') {
                app(ActivateSubscription::class)->handle($application->rider, $month, $admin);
            }
        }

        $this->command?->info('Demo data added: 1 route, 1 run, 3 riders riding, 1 approved and unpaid, 2 applications waiting.');
    }
}
