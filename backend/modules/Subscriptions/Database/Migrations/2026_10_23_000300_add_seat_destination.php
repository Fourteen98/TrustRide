<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Where a rider's ride ends at the far side of the route. Her seat's `stop_id` is her own stop, near home.
 * `destination_stop_id` is the stop she goes to: she gets off there going out, and gets on there coming back.
 * Null means the end of the route, which is where most riders go.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seats', function (Blueprint $table) {
            $table->foreignId('destination_stop_id')->nullable()->after('stop_id')->constrained('stops')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('seats', function (Blueprint $table) {
            $table->dropConstrainedForeignId('destination_stop_id');
        });
    }
};
