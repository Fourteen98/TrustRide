<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A named place where riders wait. Created once. No addresses, no geocoding.
        Schema::create('stops', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('landmark')->nullable();
            $table->string('area')->nullable()->index();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('origin_area')->nullable();
            $table->string('destination');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // The stops on a route, in order. The last one is the destination.
        Schema::create('route_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stop_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('position');
            $table->unsignedSmallInteger('minutes_from_start')->default(0);

            $table->unique(['route_id', 'stop_id']);
            $table->unique(['route_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('route_stops');
        Schema::dropIfExists('routes');
        Schema::dropIfExists('stops');
    }
};
