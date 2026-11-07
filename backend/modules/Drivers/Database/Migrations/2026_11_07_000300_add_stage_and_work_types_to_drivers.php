<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Where each driver is on the way from a daily contract to driver-partner, and which kinds of work he does.
 * Every driver today is on a daily contract and drives Ride.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->string('stage', 20)->default('daily_contract')->after('status_note');
            $table->timestamp('stage_changed_at')->nullable()->after('stage');
            $table->foreignId('stage_changed_by')->nullable()->after('stage_changed_at')->constrained('staff')->nullOnDelete();
            $table->string('stage_note', 500)->nullable()->after('stage_changed_by');
            $table->json('work_types')->nullable()->after('stage_note');
        });

        DB::table('drivers')->update(['work_types' => json_encode(['ride'])]);
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('stage_changed_by');
            $table->dropColumn(['stage', 'stage_changed_at', 'stage_note', 'work_types']);
        });
    }
};
