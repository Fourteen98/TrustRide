<?php

namespace Modules\Deliveries\Console;

use Illuminate\Console\Command;
use Modules\Deliveries\Enums\DeliveryStatus;
use Modules\Deliveries\Models\Delivery;

/**
 * Customers are not Aveo's users, so their name, phone and address are kept only as long as a parcel might be
 * asked about: `deliveries.customer_data_days` after it was done with. The parcel row itself stays, for the
 * business's invoice.
 */
class PruneCustomersCommand extends Command
{
    protected $signature = 'ox:prune-customers';

    protected $description = 'Remove customer details from parcels done with long enough ago';

    public function handle(): int
    {
        $before = now()->subDays((int) config('deliveries.customer_data_days'))->toDateString();

        $count = Delivery::query()
            ->whereNull('customer_data_removed_at')
            ->whereIn('status', [DeliveryStatus::Delivered, DeliveryStatus::Failed, DeliveryStatus::Cancelled])
            ->whereDate('delivery_date', '<', $before)
            ->update([
                'customer_name' => 'Removed',
                'customer_phone' => '',
                'address' => 'Removed',
                'landmark' => null,
                'notes' => null,
                'code_hash' => null,
                'customer_data_removed_at' => now(),
            ]);

        $this->info("Removed customer details from {$count} parcels.");

        return self::SUCCESS;
    }
}
