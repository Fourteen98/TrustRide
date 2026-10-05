<?php

namespace App\Support;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Settings staff can change in the console without a deploy.
 *
 * Code keeps reading config('subscriptions.seat_grace_days') as before. At start-up every saved
 * setting is laid over the config value of the same name, so nothing that reads config has to change.
 * A setting nobody has saved keeps the value from its config file or .env.
 */
final class Settings
{
    /** Lays the saved settings over config. Called once as the app boots. */
    public static function apply(): void
    {
        try {
            $rows = DB::table('settings')->pluck('value', 'key');
        } catch (Throwable) {
            // The table isn't there yet (a fresh install before migrating). Config values stand.
            return;
        }

        foreach ($rows as $key => $value) {
            config([$key => json_decode($value, true)]);
        }
    }

    /**
     * Saves settings and puts them into effect at once.
     *
     * @param  array<string, mixed>  $values  config name => value
     */
    public static function save(array $values, ?string $changedBy = null): void
    {
        foreach ($values as $key => $value) {
            DB::table('settings')->updateOrInsert(
                ['key' => $key],
                ['value' => json_encode($value), 'changed_by' => $changedBy, 'updated_at' => now(), 'created_at' => now()],
            );
            config([$key => $value]);
        }

        // Queue workers read settings when they start. This tells them to start again after their current job.
        rescue(fn () => Artisan::call('queue:restart'), report: false);
    }
}
