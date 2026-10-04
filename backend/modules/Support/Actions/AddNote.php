<?php

namespace Modules\Support\Actions;

use DomainException;
use Modules\Access\Models\Staff;
use Modules\Support\Enums\EntryType;
use Modules\Support\Models\SupportCase;

/** A line for colleagues only: what the driver said on the phone, what was checked. She never sees it. */
class AddNote
{
    public function handle(SupportCase $case, Staff $by, string $body): SupportCase
    {
        if (trim($body) === '') {
            throw new DomainException('Write a note.');
        }

        $case->log(EntryType::Note, trim($body), $by);

        return $case;
    }
}
