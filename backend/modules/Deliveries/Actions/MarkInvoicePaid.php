<?php

namespace Modules\Deliveries\Actions;

use DomainException;
use Modules\Access\Models\Staff;
use Modules\Deliveries\Models\BusinessInvoice;

/** Records that a business paid its month, outside the system, with the reference to find the payment by. */
class MarkInvoicePaid
{
    public function handle(BusinessInvoice $invoice, Staff $by, string $reference): BusinessInvoice
    {
        if ($invoice->isPaid()) {
            throw new DomainException('This invoice is already marked paid.');
        }

        if (trim($reference) === '') {
            throw new DomainException('Enter the payment reference, so the payment can be found later.');
        }

        $invoice->update(['paid_at' => now(), 'paid_by' => $by->id, 'payment_reference' => trim($reference)]);

        return $invoice;
    }
}
