<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $rules = [
            ['Support reply', 'support.replied', 'all', 'TrustRide: {reply}'],
            ['Incident raised', 'incident.raised', 'operator',
                'TrustRide incident. {person_name} {person_phone}: {category_label}. {message}'],
        ];

        foreach ($rules as [$name, $event, $audience, $body]) {
            DB::table('message_rules')->insert([
                'name' => $name, 'event_name' => $event, 'audience' => $audience, 'body' => $body,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('message_rules')->whereIn('event_name', ['support.replied', 'incident.raised'])->delete();
    }
};
