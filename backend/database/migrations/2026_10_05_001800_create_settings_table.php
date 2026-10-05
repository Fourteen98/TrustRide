<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Settings staff have changed in the console. A row here replaces the value of the same name
         * in a config file. No row means the config file's value stands. See App\Support\Settings.
         */
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary(); // the config name, e.g. subscriptions.seat_grace_days
            $table->json('value');
            $table->string('changed_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
