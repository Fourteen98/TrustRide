<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routes', function (Blueprint $table) {
            // The road the route follows, outbound, as Google's encoded polyline. Null until traced.
            $table->text('path')->nullable();
            $table->timestamp('path_traced_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('routes', fn (Blueprint $table) => $table->dropColumn(['path', 'path_traced_at']));
    }
};
