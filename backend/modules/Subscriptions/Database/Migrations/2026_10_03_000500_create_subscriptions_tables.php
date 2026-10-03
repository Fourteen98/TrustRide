<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What we sell.
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedSmallInteger('duration_days');
            $table->unsignedInteger('price'); // pesewas
            // 1 = one trip a day (one seat). 2 = there and back (two seats).
            $table->unsignedTinyInteger('trips_per_day')->default(1);
            $table->string('no_show_policy')->default('credit_with_notice'); // lose_day, credit_with_notice, credit_always
            // How long before departure she must tell us, for the day to be credited.
            $table->unsignedSmallInteger('notice_cutoff_minutes')->default(720);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // One driver, one vehicle, one route, one direction, one time, on set days.
        Schema::create('runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->restrictOnDelete();
            $table->foreignId('route_id')->constrained()->restrictOnDelete();
            $table->foreignId('driver_id')->constrained()->restrictOnDelete();
            $table->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $table->string('direction'); // outbound (to the destination), return (back from it)
            $table->time('departs_at');
            $table->json('days_of_week'); // ISO weekdays: 1 Monday to 7 Sunday
            // How many seats the driver holds for TrustRide on this run.
            $table->unsignedSmallInteger('seats_held');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // One rider's place on one run, at one stop.
        Schema::create('seats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rider_id')->constrained()->cascadeOnDelete();
            $table->foreignId('run_id')->constrained()->restrictOnDelete();
            $table->foreignId('stop_id')->constrained()->restrictOnDelete();
            $table->foreignId('application_id')->nullable()->constrained('rider_applications')->nullOnDelete();
            $table->string('status')->default('reserved')->index(); // reserved, active, released
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->index(['run_id', 'status']);
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rider_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->string('status')->default('active')->index(); // active, expired, cancelled
            $table->date('starts_on');
            $table->date('ends_on');
            // Copied from the plan at purchase. Changing a plan never changes what someone already bought.
            $table->unsignedInteger('price');
            $table->unsignedTinyInteger('trips_per_day');
            $table->string('no_show_policy');
            $table->unsignedSmallInteger('notice_cutoff_minutes');
            $table->unsignedSmallInteger('credited_days')->default(0);
            $table->timestamp('activated_at');
            // Null when a payment activated it. A staff id when an operator did it by hand.
            $table->foreignId('activated_by')->nullable()->constrained('staff')->nullOnDelete();
            $table->timestamp('expiring_notified_at')->nullable();
            $table->timestamps();

            $table->index(['rider_id', 'status', 'ends_on']);
        });

        // A rider telling us she won't ride on a given day.
        Schema::create('absence_notices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rider_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->timestamp('given_at');
            $table->boolean('in_time');
            $table->boolean('credited');
            $table->timestamps();

            $table->unique(['rider_id', 'date']);
        });

        foreach ([['2 weeks', 14, 3500], ['1 month', 30, 6000], ['3 months', 90, 15000]] as [$name, $days, $price]) {
            DB::table('plans')->insert([
                'name' => $name, 'duration_days' => $days, 'price' => $price,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('absence_notices');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('seats');
        Schema::dropIfExists('runs');
        Schema::dropIfExists('plans');
    }
};
