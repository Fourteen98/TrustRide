<?php

namespace Modules\Deliveries\Actions;

use App\Support\Phone;
use DomainException;
use InvalidArgumentException;
use Modules\Deliveries\Models\Business;
use Modules\Deliveries\Models\DeliveryPlan;

/**
 * Adds or changes a business. Choosing a plan copies its fee, allowance, overage fee and limit rule onto the
 * business; any of them can then be changed for this business alone, which is how a Custom plan is agreed.
 */
class SaveBusiness
{
    public function handle(?Business $business, array $data): Business
    {
        try {
            $data['phone'] = Phone::normalise((string) ($data['phone'] ?? ''));
        } catch (InvalidArgumentException $e) {
            throw new DomainException($e->getMessage());
        }

        $planChanged = isset($data['delivery_plan_id']) && (int) $data['delivery_plan_id'] !== $business?->delivery_plan_id;

        if ($planChanged && ($plan = DeliveryPlan::find($data['delivery_plan_id']))) {
            foreach (['monthly_fee', 'delivery_allowance', 'overage_fee', 'at_limit'] as $field) {
                if (! array_key_exists($field, $data) || $data[$field] === null || $data[$field] === '') {
                    $data[$field] = $plan->{$field};
                }
            }
        }

        if (! isset($data['monthly_fee']) || $data['monthly_fee'] === '') {
            throw new DomainException('Choose a plan, or enter the monthly fee agreed with this business.');
        }

        $business ??= new Business;
        $business->fill($data)->save();

        return $business;
    }
}
