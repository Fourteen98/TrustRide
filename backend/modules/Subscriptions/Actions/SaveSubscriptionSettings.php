<?php

namespace Modules\Subscriptions\Actions;

use App\Support\Settings;
use DomainException;
use Modules\Access\Models\Staff;

/**
 * The numbers the business tunes without a developer: how long seats are held, when riders are
 * told their plan is ending, and how automatic payment behaves. Saved by staff in the console.
 */
class SaveSubscriptionSettings
{
    /** Setting => [label, lowest, highest]. The same list drives the console form. */
    public const FIELDS = [
        'expiring_notice_days' => ['Tell her this many days before her plan ends', 0, 14],
        'auto_renew_charge_days_before' => ['Take automatic payments this many days before the plan ends', 0, 7],
        'auto_renew_max_failures' => ['Switch automatic payment off after this many failed charges in a row', 1, 10],
        'seat_grace_days' => ['Keep her seat this many days after her plan ends', 0, 30],
        'reservation_days' => ['Hold a reserved seat this many days before she pays', 1, 30],
        'rider_retention_days' => ['Delete a rider\'s data this many days after she stops riding (0 keeps it until she asks)', 0, 3650],
    ];

    /** @return array<string, int> what is in force now */
    public static function current(): array
    {
        return collect(self::FIELDS)->map(fn (array $field, string $key) => (int) config("subscriptions.{$key}"))->all();
    }

    /** @param array<string, int|string> $values */
    public function handle(array $values, Staff $by): void
    {
        $clean = [];

        foreach (self::FIELDS as $key => [$label, $min, $max]) {
            $value = $values[$key] ?? null;

            if (! is_numeric($value) || (int) $value != $value || $value < $min || $value > $max) {
                throw new DomainException("\"{$label}\" must be a whole number from {$min} to {$max}.");
            }

            $clean["subscriptions.{$key}"] = (int) $value;
        }

        // She must hear about a charge before it is taken, never after.
        if ($clean['subscriptions.auto_renew_charge_days_before'] > $clean['subscriptions.expiring_notice_days']) {
            throw new DomainException('Automatic payments can\'t be taken before she is told. Tell her at least as many days ahead as the payment is taken.');
        }

        Settings::save($clean, $by->name);
    }
}
