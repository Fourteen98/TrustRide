<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * The company is now Aveo, previously TrustRide. Organisation 1 is the company itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('organisations')->where('slug', 'trustride')->update(['name' => 'Aveo', 'slug' => 'aveo', 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('organisations')->where('slug', 'aveo')->update(['name' => 'TrustRide', 'slug' => 'trustride', 'updated_at' => now()]);
    }
};
