<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organisations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_platform_owner')->default(false);
            $table->timestamps();
        });

        // TrustRide is organisation 1. Partner companies come later.
        DB::table('organisations')->insert([
            'name' => 'TrustRide',
            'slug' => 'trustride',
            'is_platform_owner' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('organisations');
    }
};
