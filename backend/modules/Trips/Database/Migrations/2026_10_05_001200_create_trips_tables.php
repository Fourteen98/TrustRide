<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * One run on one day. The row is created when the trip starts. A run with no row today has not left yet.
         * The newest position is kept on the trip itself, whichever phone sent it.
         */
        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            // What the share link carries. Random, so a link can't be guessed from another.
            $table->ulid('public_id')->unique();
            $table->foreignId('run_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('status')->default('running')->index(); // running, ended
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            // Who pressed start and end: a crew account, or a member of staff setting it by hand.
            $table->foreignId('started_by_account')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('ended_by_account')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('started_by_staff')->nullable()->constrained('staff')->nullOnDelete();
            $table->foreignId('ended_by_staff')->nullable()->constrained('staff')->nullOnDelete();
            // The furthest stop the van has reached, from its position or from a pickup being marked there.
            $table->foreignId('reached_stop_id')->nullable()->constrained('stops')->nullOnDelete();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamp('located_at')->nullable();
            $table->timestamps();

            $table->unique(['run_id', 'date']);
        });

        Schema::create('trip_pickups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rider_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stop_id')->constrained()->restrictOnDelete();
            $table->string('status'); // picked_up, missed
            $table->foreignId('marked_by_account')->nullable()->constrained('accounts')->nullOnDelete();
            $table->timestamp('marked_at');
            $table->timestamps();

            $table->unique(['trip_id', 'rider_id']);
        });

        // Every position a crew phone sent. History only. The map reads the trip row.
        Schema::create('location_pings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->unsignedSmallInteger('accuracy')->nullable(); // metres
            $table->timestamp('recorded_at'); // on the phone
            $table->timestamp('created_at')->nullable(); // when it reached us

            $table->index(['trip_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_pings');
        Schema::dropIfExists('trip_pickups');
        Schema::dropIfExists('trips');
    }
};
