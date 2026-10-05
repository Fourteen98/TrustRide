<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** "The day before" was fixed in the words. The day is now a setting, so the message names the date instead. */
    public function up(): void
    {
        DB::table('message_rules')
            ->where('event_name', 'auto_renewal.upcoming')
            ->where('body', 'like', '%the day before to renew it%') // only if nobody has reworded it
            ->update(['body' => '{rider_name}, your TrustRide plan ends on {ends_on_text}. We will take {amount} from your {network} wallet ending {wallet} on {charge_on_text} to renew it. To stop this, open the app.']);
    }

    public function down(): void {}
};
