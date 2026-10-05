<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $rules = [
            ['Automatic renewal on', 'auto_renewal.started',
                '{rider_name}, automatic renewal is on. TrustRide will take {amount} from your {network} wallet ending {wallet} each time your {plan} plan ends. Stop it any time in the app.'],
            ['Automatic renewal coming', 'auto_renewal.upcoming',
                '{rider_name}, your TrustRide plan ends on {ends_on_text}. We will take {amount} from your {network} wallet ending {wallet} the day before to renew it. To stop this, open the app.'],
            ['Automatic renewal stopped', 'auto_renewal.stopped',
                '{rider_name}, we could not take your TrustRide payment, so automatic renewal is now off. Open the app and pay to keep your seat.'],
        ];

        foreach ($rules as [$name, $event, $body]) {
            DB::table('message_rules')->insert([
                'name' => $name, 'event_name' => $event, 'audience' => 'rider', 'body' => $body,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('message_rules')->where('event_name', 'like', 'auto_renewal.%')->delete();
    }
};
