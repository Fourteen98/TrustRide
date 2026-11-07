<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * App notifications as the van nears each stop. App only: an SMS that arrives late is worse than none,
 * and these go out several times a trip.
 */
return new class extends Migration
{
    private const EVENTS = ['van.approaching', 'stop.approaching', 'crew.approaching'];

    public function up(): void
    {
        $rules = [
            ['Van is nearly at her stop', 'van.approaching', 'rider',
                '{name}, your van is nearly at {stop}. Car {car_number}, {car_colour}. Be ready at the stop.'],
            ['Her stop to get down is next', 'stop.approaching', 'rider',
                '{name}, {stop} is next. Get ready to get down.'],
            ['Crew: next stop', 'crew.approaching', 'all',
                '{stop} next: {moves}.'],
        ];

        foreach ($rules as [$name, $event, $audience, $body]) {
            DB::table('message_rules')->insert([
                'name' => $name, 'event_name' => $event, 'audience' => $audience, 'channel' => 'push', 'body' => $body,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('message_rules')->whereIn('event_name', self::EVENTS)->delete();
    }
};
