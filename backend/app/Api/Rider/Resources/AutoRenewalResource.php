<?php

namespace App\Api\Rider\Resources;

use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Subscriptions\Enums\AutoRenewalStatus;
use Modules\Subscriptions\Models\AutoRenewal;

/** @mixin AutoRenewal */
class AutoRenewalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $otp = $this->status === AutoRenewalStatus::Pending && $this->mandate?->verification === 'otp';

        return [
            /**
             * pending: she must approve on her phone, keep asking. active: on. off: stopped, or never approved.
             *
             * @var 'pending'|'active'|'off'
             */
            'status' => $this->status->value,
            'plan' => $this->plan->name,
            /** What is taken each time, e.g. "GHS 60" */
            'amount_label' => Money::format($this->plan->price),
            /** e.g. "MTN MoMo" */
            'network' => config('payments.networks.'.$this->mandate?->network.'.label', ''),
            /** The last four digits of the wallet */
            'wallet' => $this->mandate?->phone ? substr($this->mandate->phone, -4) : '',
            /** @var bool True if she must type a code she was texted. Send it to `POST auto-renewal/code`. */
            'needs_code' => $otp,
            /** The letters before the dash in the code she was texted, to show next to the field */
            'code_prefix' => $otp ? $this->mandate->otp_prefix : null,
            /** @var string A sentence for her, saying where things stand */
            'message' => match ($this->status) {
                AutoRenewalStatus::Pending => $otp
                    ? 'Enter the code we sent to your phone.'
                    : 'Approve the direct debit request on your phone with your mobile money PIN.',
                AutoRenewalStatus::Active => 'Your plan renews by itself. You can stop it at any time.',
                AutoRenewalStatus::Off => $this->last_error ?: 'Automatic renewal is off.',
            },
        ];
    }
}
