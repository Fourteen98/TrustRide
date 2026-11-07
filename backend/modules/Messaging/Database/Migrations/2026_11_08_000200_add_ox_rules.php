<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Aveo Ox messages. Customers have no app, so these go by SMS. The code a customer gives the driver is not
 * here: it goes out directly when the parcel is collected, so it never sits in the message log.
 * The business's "delivered" message is off until a business asks for it, since a busy shop would get dozens a day.
 */
return new class extends Migration
{
    private const EVENTS = ['delivery.received', 'delivery.failed', 'delivery.delivered'];

    public function up(): void
    {
        $rules = [
            ['Ox: parcel on the way to the customer', 'delivery.received', true,
                '{business_name} is sending your order with Aveo Ox. It should reach you {promise}. Follow it: {track_url}'],
            ['Ox: couldn\'t deliver', 'delivery.failed', true,
                'We couldn\'t deliver your {business_name} order today: {failure_reason}. Aveo will call you to arrange another day.'],
            ['Ox: tell the business it was delivered', 'delivery.delivered', false,
                'Aveo Ox: {customer_name} has her parcel {reference}.'],
        ];

        foreach ($rules as [$name, $event, $active, $body]) {
            DB::table('message_rules')->insert([
                'name' => $name, 'event_name' => $event, 'audience' => 'all', 'channel' => 'sms', 'body' => $body,
                'is_active' => $active, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('message_rules')->whereIn('event_name', self::EVENTS)->delete();
    }
};
