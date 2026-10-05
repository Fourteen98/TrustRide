<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * A phone that can receive app notifications. Keyed by phone number, because that is the only
         * way Messaging knows a person. Someone with two phones has two rows.
         */
        Schema::create('push_devices', function (Blueprint $table) {
            $table->id();
            $table->string('phone')->index();
            // The address the push service gave this install of the app. One install, one row.
            $table->string('token')->unique();
            $table->string('platform')->nullable(); // ios, android
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_devices');
    }
};
