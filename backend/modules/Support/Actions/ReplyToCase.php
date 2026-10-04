<?php

namespace Modules\Support\Actions;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Access\Models\Staff;
use Modules\Support\Enums\EntryType;
use Modules\Support\Events\CaseReplied;
use Modules\Support\Models\SupportCase;

/**
 * Staff answer the person. She sees it in the app and is messaged, so it reaches her with the app closed.
 * Replying to a case nobody has taken takes it.
 */
class ReplyToCase
{
    public function handle(SupportCase $case, Staff $by, string $body): SupportCase
    {
        $body = trim($body);

        if ($body === '') {
            throw new DomainException('Write a reply.');
        }

        if (! $case->isOpen()) {
            throw new DomainException('This case is resolved. Reopen it to reply.');
        }

        return DB::transaction(function () use ($case, $by, $body) {
            if ($case->assigned_to === null) {
                $case->update(['assigned_to' => $by->id]);
            }

            $case->log(EntryType::Reply, $body, $by);

            CaseReplied::dispatch($case, ['reply' => $body]);

            return $case;
        });
    }
}
