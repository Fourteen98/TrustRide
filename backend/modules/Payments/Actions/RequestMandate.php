<?php

namespace Modules\Payments\Actions;

use DomainException;
use Illuminate\Support\Str;
use Modules\Payments\Enums\MandateStatus;
use Modules\Payments\Gateways\GatewayManager;
use Modules\Payments\Models\PaymentMandate;

/**
 * Asks a wallet's owner to approve direct debit: money taken later without a prompt each time.
 * She approves once on her phone. The answer comes as a MandateApproved or MandateFailed event.
 *
 * An owner has one mandate. Asking again withdraws the old one first.
 */
class RequestMandate
{
    public function __construct(private GatewayManager $gateways, private CancelMandate $cancel) {}

    public function handle(string $ownerType, int $ownerId, string $phone, string $network): PaymentMandate
    {
        if (! self::supports($network)) {
            throw new DomainException('Automatic payment is not available on that network. Pay once instead.');
        }

        PaymentMandate::query()
            ->where('owner_type', $ownerType)->where('owner_id', $ownerId)
            ->whereIn('status', [MandateStatus::Pending, MandateStatus::Active])
            ->get()
            ->each(fn (PaymentMandate $old) => $this->cancel->handle($old, quietly: true));

        $gateway = $this->gateways->gateway();

        $mandate = PaymentMandate::query()->create([
            'reference' => 'TR-M-'.Str::ulid(),
            'owner_type' => $ownerType,
            'owner_id' => $ownerId,
            'phone' => $phone,
            'network' => $network,
            'provider' => $gateway->key(),
        ]);

        $result = $gateway->requestMandate($mandate);
        $mandate->remember('create', $result->raw);

        if (! $result->accepted) {
            // The caller hears about this through the exception, so no MandateFailed event is fired.
            $mandate->update(['status' => MandateStatus::Failed, 'failure_reason' => str((string) $result->error)->limit(250)->toString()]);

            throw new DomainException('We couldn\'t set up automatic payment. Nothing has been taken. Try again, or pay once.');
        }

        $mandate->update([
            'provider_mandate_id' => $result->providerId,
            'verification' => $result->verification,
            'otp_prefix' => $result->otpPrefix,
        ]);

        return $mandate;
    }

    /** True if a network offers direct debit at all. */
    public static function supports(string $network): bool
    {
        return (bool) config("payments.networks.{$network}.debit_channel");
    }
}
