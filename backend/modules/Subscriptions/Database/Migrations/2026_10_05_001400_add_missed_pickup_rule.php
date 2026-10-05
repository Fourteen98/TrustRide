<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What happens to her day when the van came and the mate did not find her. Off means nothing: she is only told.
        Schema::table('plans', fn (Blueprint $table) => $table->boolean('credit_missed_pickup')->default(false));
        // Copied from the plan at purchase, like the other rules.
        Schema::table('subscriptions', fn (Blueprint $table) => $table->boolean('credit_missed_pickup')->default(false));
        // rider: she told us. missed_pickup: the mate marked her not at the stop and her plan gives the day back.
        Schema::table('absence_notices', fn (Blueprint $table) => $table->string('source')->default('rider'));
    }

    public function down(): void
    {
        Schema::table('absence_notices', fn (Blueprint $table) => $table->dropColumn('source'));
        Schema::table('subscriptions', fn (Blueprint $table) => $table->dropColumn('credit_missed_pickup'));
        Schema::table('plans', fn (Blueprint $table) => $table->dropColumn('credit_missed_pickup'));
    }
};
