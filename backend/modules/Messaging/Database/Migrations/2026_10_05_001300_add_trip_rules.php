<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $rules = [
            ['Van has set off', 'trip.started', 'rider',
                '{name}, your TrustRide van has set off. Car {car_number}, {car_colour}. Driver {driver_name}. Follow it in the app.'],
            ['Not at the stop', 'rider.missed', 'rider',
                '{rider_name}, the van came to {stop} and the mate did not find you. If that is wrong, tell us in the app under Help.'],
        ];

        foreach ($rules as [$name, $event, $audience, $body]) {
            DB::table('message_rules')->insert([
                'name' => $name, 'event_name' => $event, 'audience' => $audience, 'body' => $body,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('message_rules')->whereIn('event_name', ['trip.started', 'rider.missed'])->delete();
    }
};
