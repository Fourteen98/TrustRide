<?php

namespace Modules\Support\Actions;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Access\Models\Staff;
use Modules\Support\Enums\CaseStatus;
use Modules\Support\Enums\EntryType;
use Modules\Support\Events\CaseResolved;
use Modules\Support\Models\SupportCase;

/** Closes a case. What was done about it must be written down, because an incident is read again later. */
class ResolveCase
{
    public function handle(SupportCase $case, Staff $by, string $resolution): SupportCase
    {
        if (! $case->isOpen()) {
            throw new DomainException('This case is already resolved.');
        }

        if (trim($resolution) === '') {
            throw new DomainException('Say what was done about it.');
        }

        return DB::transaction(function () use ($case, $by, $resolution) {
            $case->update([
                'status' => CaseStatus::Resolved,
                'resolution' => trim($resolution),
                'resolved_at' => now(),
                'resolved_by' => $by->id,
            ]);
            $case->log(EntryType::Resolved, trim($resolution), $by);

            CaseResolved::dispatch($case);

            return $case;
        });
    }
}
