<?php

namespace Modules\Payments\Actions;

use DomainException;
use Illuminate\Support\Str;
use Modules\Payments\Enums\PaymentStatus;
use Modules\Payments\Gateways\GatewayManager;
use Modules\Payments\Models\Payment;

/**
 * Asks the gateway to prompt a phone for money. The only way a payment starts.
 *
 * The caller says what is being paid for as a type and an id, e.g. ('plan_purchase', 12).
 * The answer comes later as a PaymentSucceeded or PaymentFailed event carrying the same type and id.
 * Returning normally means "she has been prompted", never "she has paid".
 */
class StartPayment
{
    public function __construct(private GatewayManager $gateways) {}

    public function handle(string $payableType, int $payableId, int $amount, string $phone, string $network, string $description): Payment
    {
        if ($amount <= 0) {
            throw new DomainException('There is nothing to pay.');
        }

        if (! array_key_exists($network, config('payments.networks'))) {
            throw new DomainException('Choose the mobile money network to pay from.');
        }

        $gateway = $this->gateways->gateway();

        // The row is saved before the gateway is called. If the call dies halfway, the payment still
        // exists here, and the status check can find out what the gateway did with it.
        $payment = Payment::query()->create([
            'reference' => 'TR-'.Str::ulid(),
            'payable_type' => $payableType,
            'payable_id' => $payableId,
            'description' => $description,
            'amount' => $amount,
            'phone' => $phone,
            'network' => $network,
            'provider' => $gateway->key(),
        ]);

        $result = $gateway->charge($payment);
        $payment->remember('create', $result->raw);

        if (! $result->accepted) {
            // The caller hears about this through the exception, so no PaymentFailed event is fired.
            $payment->update(['status' => PaymentStatus::Failed, 'failure_reason' => str((string) $result->error)->limit(250)->toString()]);

            throw new DomainException('We couldn\'t start the payment. You have not been charged. Try again in a moment.');
        }

        if ($result->transactionId) {
            $payment->update(['provider_transaction_id' => $result->transactionId]);
        }

        return $payment;
    }
}
