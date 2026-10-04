<?php

namespace Modules\Support\Actions;

use Modules\Access\Models\Staff;
use Modules\Support\Enums\EntryType;
use Modules\Support\Models\SupportCase;

/** Take a case, or hand it to a colleague. The timeline says who gave it to whom. */
class AssignCase
{
    public function handle(SupportCase $case, Staff $to, Staff $by): SupportCase
    {
        if ($case->assigned_to === $to->id) {
            return $case;
        }

        $case->update(['assigned_to' => $to->id]);
        $case->log(EntryType::Assigned, $to->is($by) ? "{$by->name} took it." : "{$by->name} handed it to {$to->name}.", $by);

        return $case;
    }
}
