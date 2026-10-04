<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('message_rules')->insert([
            'name' => 'Payment failed', 'event_name' => 'purchase.failed', 'audience' => 'rider',
            'body' => '{rider_name}, your TrustRide payment of {amount} did not go through. Open the app to try again.',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('message_rules')->where('event_name', 'purchase.failed')->delete();
    }
};
