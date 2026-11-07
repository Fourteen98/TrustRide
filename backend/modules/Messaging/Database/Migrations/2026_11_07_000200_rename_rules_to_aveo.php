<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * The company is now Aveo, previously TrustRide. Every message rule that names it is reworded, including
 * rules operators wrote themselves in the console.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->rename('TrustRide', 'Aveo');
    }

    public function down(): void
    {
        $this->rename('Aveo', 'TrustRide');
    }

    private function rename(string $from, string $to): void
    {
        DB::table('message_rules')->where('body', 'like', "%{$from}%")->get(['id', 'body'])
            ->each(fn ($rule) => DB::table('message_rules')->where('id', $rule->id)
                ->update(['body' => str_replace($from, $to, $rule->body), 'updated_at' => now()]));
    }
};
