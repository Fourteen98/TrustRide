<?php

use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Artisan;

/*
 * Wipes the local database and fills it with a fresh demo dated from today: five weeks of trips,
 * payments and riders at every stage, and today replayed up to the present minute. See DemoSeeder.
 */
Artisan::command('demo:reset {--real-times : Keep the fixed 06:00 and 06:15 timetable instead of building today around now} {--drive : Then keep the vans moving in real time}', function () {
    if (app()->isProduction()) {
        $this->error('demo:reset wipes the database. It does not run in production.');

        return 1;
    }

    $this->call('migrate:fresh', ['--force' => true]);

    // Today's timetable is built around this moment, unless --real-times.
    app()->instance(DemoSeeder::class, tap(new DemoSeeder, fn (DemoSeeder $seeder) => $seeder->liveTimes = ! $this->option('real-times')));
    $this->call('db:seed', ['--class' => DemoSeeder::class, '--force' => true]);

    $this->info('Log in to the console with ADMIN_EMAIL and ADMIN_PASSWORD from .env (admin@aveo.test and change-me-now by default).');
    $this->line('Riders: 0245550101 Ama (Kasoa), 0245550102 Esi (Mallam, 2 people paying the fare today), 0245550107 Efua (3 on her plan), 0245550111 Adjoa (Madina).');
    $this->line('Drivers: 0245550001 and 0245550005 (Kasoa), 0245550003 and 0245550007 (Madina). Each mate is the driver\'s number plus one.');
    $this->line('Ox drivers: 0245550201 Kojo (a Lapaz run out now, under Parcels) and 0245550202 Kwesi. Ox customers\' pages are linked from Ox > Parcels.');

    if ($this->option('drive')) {
        $this->call('demo:drive');
    } else {
        $this->line('The van on the road stands still from now. Run `php artisan demo:drive` to keep it moving.');
    }

    return 0;
})->purpose('Wipe the local database and fill it with a fresh demo dated from now');

/*
 * Drives today's demo vans in real time until none is left: they set off when due, move along the road, the mate marks
 * riders at each stop, and trips end on arrival. Leave it running in a terminal while testing the app. Ctrl+C stops it.
 */
Artisan::command('demo:drive {--every=10 : Seconds between positions}', function () {
    if (app()->isProduction()) {
        $this->error('Not in production.');

        return 1;
    }

    $seeder = new DemoSeeder;

    foreach ($seeder->today() as $line) {
        $this->line($line);
    }

    $this->info('Driving. Leave this running while you test. Ctrl+C stops it.');
    $seeder->drive(fn (string $line) => $this->line(now()->format('H:i:s').'  '.$line), (int) $this->option('every'));

    return 0;
})->purpose('Keep the demo vans moving in real time, for testing the app');
