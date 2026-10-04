<?php

namespace Modules\Support\Actions;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Access\Models\Staff;
use Modules\Support\Enums\CaseStatus;
use Modules\Support\Enums\EntryType;
use Modules\Support\Models\SupportCase;

class ReopenCase
{
    public function handle(SupportCase $case, Staff $by): SupportCase
    {
        if ($case->isOpen()) {
            throw new DomainException('This case is already open.');
        }

        return DB::transaction(function () use ($case, $by) {
            $other = $case->account_id !== null && SupportCase::query()
                ->where('account_id', $case->account_id)->where('status', CaseStatus::Open)->exists();

            if ($other) {
                throw new DomainException('She has a newer open case. Work on that one.');
            }

            $case->update(['status' => CaseStatus::Open, 'resolved_at' => null, 'resolved_by' => null]);
            $case->log(EntryType::Reopened, null, $by);

            return $case;
        });
    }
}
