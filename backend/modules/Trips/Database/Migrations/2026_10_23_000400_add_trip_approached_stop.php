<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The furthest stop the van has been announced as nearing. People getting on or off there have been told,
 * so each stop is announced once per trip, however many positions arrive near it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->foreignId('approached_stop_id')->nullable()->after('reached_stop_id')->constrained('stops')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approached_stop_id');
        });
    }
};
