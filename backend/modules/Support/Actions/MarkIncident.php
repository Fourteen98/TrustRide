<?php

namespace Modules\Support\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Access\Models\Staff;
use Modules\Support\Enums\EntryType;
use Modules\Support\Enums\Severity;
use Modules\Support\Events\IncidentRaised;
use Modules\Support\Models\SupportCase;

/**
 * Marks a case as an incident: something Aveo must be able to account for later.
 * Calling it again only changes how serious it is.
 */
class MarkIncident
{
    public function handle(SupportCase $case, Staff $by, Severity $severity): SupportCase
    {
        return DB::transaction(function () use ($case, $by, $severity) {
            $already = $case->is_incident;

            $case->update(['is_incident' => true, 'severity' => $severity]);
            $case->log(EntryType::Incident, ($already ? 'Severity changed to ' : 'Severity ').$severity->value.'.', $by);

            if (! $already) {
                IncidentRaised::dispatch($case, ['message' => (string) $case->entries()->oldest('id')->value('body')]);
            }

            return $case;
        });
    }
}
