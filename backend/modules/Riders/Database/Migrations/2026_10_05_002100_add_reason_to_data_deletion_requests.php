<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // requested: she asked. retention: she stopped riding long enough ago that we no longer keep her data.
        Schema::table('data_deletion_requests', fn (Blueprint $table) => $table->string('reason')->default('requested'));
    }

    public function down(): void
    {
        Schema::table('data_deletion_requests', fn (Blueprint $table) => $table->dropColumn('reason'));
    }
};
