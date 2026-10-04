<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // When this event happens, tell these people, on this channel, with these words.
        Schema::create('message_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('event_name')->index();
            // Which of the event's recipients get it: rider, driver, mate, or "all".
            $table->string('audience')->default('all');
            // preferred (the person's own choice, with fallback), or one fixed channel: sms, whatsapp, push.
            $table->string('channel')->default('preferred');
            // The words, with {placeholders} filled from the event.
            $table->text('body');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // The delivery log. One row per message to one person.
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_log_id')->nullable()->constrained('event_log')->cascadeOnDelete();
            $table->foreignId('message_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->string('recipient_phone')->index();
            $table->string('recipient_name')->nullable();
            $table->string('recipient_role')->nullable();
            // The channel it went out on, or the last one tried.
            $table->string('channel')->nullable();
            $table->text('body');
            $table->string('status')->default('queued')->index(); // queued, sent, failed
            // Every channel tried, in order: [{channel, ok, error, at}]
            $table->json('attempts')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            // One event, one rule, one person: one message. Makes delivery safe to run twice.
            $table->unique(['event_log_id', 'message_rule_id', 'recipient_phone'], 'messages_once_per_event_rule_person');
        });

        $rules = [
            ['Application approved', 'application.approved', 'rider',
                'Good news {rider_name}. Your TrustRide seat on {route} from {stop} is reserved. Open the app and choose a plan to make it yours.'],
            ['Application rejected', 'application.rejected', 'rider',
                'Sorry {rider_name}, we can\'t give you a TrustRide seat right now. {reason}. You can apply again for another stop or time.'],
            ['Receipt', 'subscription.activated', 'rider',
                'You\'re in, {rider_name}. Car {car_number}, {car_colour}. Driver {driver_name}. Be at {stop} by {time}. Your {plan} plan runs to {ends_on_text}.'],
            ['Subscription ending', 'subscription.expiring', 'rider',
                '{rider_name}, your TrustRide subscription ends on {ends_on_text}. Renew to keep your seat at {stop}.'],
            ['Subscription ended', 'subscription.expired', 'rider',
                '{rider_name}, your TrustRide subscription has ended. Renew in the next few days to keep your seat.'],
            ['Message to a run', 'run.announcement', 'rider', '{message}'],
            ['Driver approved', 'driver.approved', 'driver',
                'Welcome to TrustRide, {driver_name}. You are approved. We will call you about your run.'],
            ['Driver paid', 'driver.statement_paid', 'driver',
                '{driver_name}, TrustRide has paid your {month} commission: GHS {amount}.'],
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
        Schema::dropIfExists('messages');
        Schema::dropIfExists('message_rules');
    }
};
