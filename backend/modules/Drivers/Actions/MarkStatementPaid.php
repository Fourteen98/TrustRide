<?php

namespace Modules\Drivers\Actions;

use DomainException;
use Modules\Access\Models\Staff;
use Modules\Drivers\Events\DriverStatementPaid;
use Modules\Drivers\Models\DriverStatement;

/** Records that a driver was paid for a month. The money moves outside the system. This only keeps the record. */
class MarkStatementPaid
{
    public function handle(DriverStatement $statement, Staff $by, string $reference): DriverStatement
    {
        if ($statement->isPaid()) {
            throw new DomainException('That month has already been paid.');
        }

        if ($statement->assessment_outcome === null) {
            throw new DomainException('Record the assessment before paying.');
        }

        if (trim($reference) === '') {
            throw new DomainException('Enter the payment reference.');
        }

        $statement->update(['paid_at' => now(), 'paid_by' => $by->id, 'payment_reference' => trim($reference)]);

        $driver = $statement->driver;
        DriverStatementPaid::dispatch($driver->id, $driver->name, $driver->phone, $statement->period->format('F Y'), $statement->total());

        return $statement;
    }
}
