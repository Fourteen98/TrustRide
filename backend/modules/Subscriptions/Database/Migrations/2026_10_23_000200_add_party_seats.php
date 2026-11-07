<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Seats for the people travelling with a rider.
 *
 * On a van where they are added to her plan, her seat holds her and them (`party`), her plan is bought for that many
 * (`seats`), and they count against the seats held on the run. On a van where they pay the fare, she holds seats
 * for a day (`guest_seats`), and each person pays the mate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seats', function (Blueprint $table) {
            $table->unsignedTinyInteger('party')->default(1); // her, plus people on her plan
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->unsignedTinyInteger('seats')->default(1);
        });

        Schema::table('plan_purchases', function (Blueprint $table) {
            $table->unsignedTinyInteger('seats')->default(1);
        });

        // What each automatic charge is for. Null keeps whatever her seat holds.
        Schema::table('auto_renewals', function (Blueprint $table) {
            $table->unsignedTinyInteger('seats')->nullable();
        });

        Schema::create('guest_seats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rider_id')->constrained()->cascadeOnDelete();
            $table->foreignId('run_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedTinyInteger('seats');
            $table->timestamps();

            $table->unique(['rider_id', 'run_id', 'date']);
            $table->index(['run_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_seats');
        Schema::table('auto_renewals', fn (Blueprint $table) => $table->dropColumn('seats'));
        Schema::table('plan_purchases', fn (Blueprint $table) => $table->dropColumn('seats'));
        Schema::table('subscriptions', fn (Blueprint $table) => $table->dropColumn('seats'));
        Schema::table('seats', fn (Blueprint $table) => $table->dropColumn('party'));
    }
};
