<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Who owns a van, and whether riders may hold seats on it for people travelling with them.
 * Existing vans are TrustRide's, with extra seats off, which is how they worked before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('ownership')->default('own'); // own, partner
            $table->string('owner_name')->nullable(); // the partner's name, for a van we don't own
            $table->string('extra_seats')->default('off'); // off, fare, plan
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn(['ownership', 'owner_name', 'extra_seats']);
        });
    }
};
