<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Where and when a rider got down, as she told the app. It tells us where riders really go,
 * which is what the next route is planned from.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_pickups', function (Blueprint $table) {
            $table->timestamp('alighted_at')->nullable();
            $table->foreignId('alighted_stop_id')->nullable()->constrained('stops')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('trip_pickups', function (Blueprint $table) {
            $table->dropConstrainedForeignId('alighted_stop_id');
            $table->dropColumn('alighted_at');
        });
    }
};
