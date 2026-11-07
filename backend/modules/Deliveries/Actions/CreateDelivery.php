<?php

namespace Modules\Deliveries\Actions;

use App\Support\Phone;
use Carbon\CarbonInterface;
use DomainException;
use InvalidArgumentException;
use Modules\Access\Models\Staff;
use Modules\Deliveries\Enums\LimitPolicy;
use Modules\Deliveries\Events\DeliveryReceived;
use Modules\Deliveries\Models\Business;
use Modules\Deliveries\Models\Delivery;
use Modules\Deliveries\Models\Zone;

/**
 * A business sends one parcel. It goes on its zone's run today if it is in before the zone's cut-off, otherwise
 * tomorrow. The customer is told when to expect it and given the link to follow it.
 *
 * A business on "stop at the limit" can't send more than its month's allowance. Otherwise extra parcels are
 * taken and charged as overage on the month's invoice.
 */
class CreateDelivery
{
    public function handle(Business $business, array $data, ?Staff $by = null, ?CarbonInterface $at = null): Delivery
    {
        $this->check($business, $data, 1, $at);

        $zone = Zone::query()->findOrFail($data['zone_id']);

        $delivery = $business->deliveries()->create([
            'zone_id' => $zone->id,
            'delivery_date' => $zone->deliveryDateFor($at ?? now())->toDateString(),
            'reference' => self::text($data['reference'] ?? null),
            'customer_name' => trim($data['customer_name']),
            'customer_phone' => Phone::normalise($data['customer_phone']),
            'address' => trim($data['address']),
            'landmark' => self::text($data['landmark'] ?? null),
            'notes' => self::text($data['notes'] ?? null),
            'created_by' => $by?->id,
        ]);

        DeliveryReceived::dispatch($delivery);

        return $delivery;
    }

    /**
     * Everything that would make a parcel refused, without saving anything: so a file of many can be checked first.
     * $count is how many parcels are about to be added together.
     */
    public function check(Business $business, array $data, int $count = 1, ?CarbonInterface $at = null): void
    {
        if (! $business->isActive()) {
            throw new DomainException("{$business->name} is paused. Switch it back on before sending parcels.");
        }

        foreach (['customer_name' => 'the customer\'s name', 'customer_phone' => 'the customer\'s phone', 'address' => 'the address'] as $field => $words) {
            if (trim((string) ($data[$field] ?? '')) === '') {
                throw new DomainException("Enter {$words}.");
            }
        }

        try {
            Phone::normalise((string) $data['customer_phone']);
        } catch (InvalidArgumentException) {
            throw new DomainException("{$data['customer_phone']} is not a Ghana phone number.");
        }

        $zone = Zone::query()->find($data['zone_id'] ?? null);

        if (! $zone || ! $zone->is_active) {
            throw new DomainException('Choose a zone Aveo Ox delivers to.');
        }

        if ($business->at_limit === LimitPolicy::Stop && $business->delivery_allowance !== null) {
            $date = $zone->deliveryDateFor($at ?? now());
            $used = $business->deliveriesIn($date);

            if ($used + $count > $business->delivery_allowance) {
                throw new DomainException("{$business->name} has used {$used} of its {$business->delivery_allowance} deliveries for {$date->format('F')}. Its plan stops at the limit.");
            }
        }
    }

    private static function text(?string $value): ?string
    {
        return $value === null || trim($value) === '' ? null : trim($value);
    }
}
