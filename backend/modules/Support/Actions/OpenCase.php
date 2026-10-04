<?php

namespace Modules\Support\Actions;

use App\Support\Phone;
use DomainException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Access\Models\Account;
use Modules\Access\Models\Staff;
use Modules\Support\Enums\CaseCategory;
use Modules\Support\Enums\CaseStatus;
use Modules\Support\Enums\EntryType;
use Modules\Support\Events\CaseOpened;
use Modules\Support\Models\SupportCase;

/**
 * An operator records a case for someone who called or sent a WhatsApp, so it is not lost.
 * If the number belongs to an app account, the case is tied to it and she sees it in the app.
 */
class OpenCase
{
    /** @param array{name: string, phone: string, category: string, message: string, source?: string} $data */
    public function handle(Staff $by, array $data): SupportCase
    {
        try {
            $phone = Phone::normalise($data['phone']);
        } catch (InvalidArgumentException $e) {
            throw new DomainException($e->getMessage());
        }

        return DB::transaction(function () use ($by, $data, $phone) {
            $account = Account::query()->where('phone', $phone)->first();

            if ($account && SupportCase::query()->where('account_id', $account->id)->where('status', CaseStatus::Open)->exists()) {
                throw new DomainException('She already has an open case. Add a note to that one.');
            }

            $case = SupportCase::query()->create([
                'account_id' => $account?->id,
                'name' => $data['name'],
                'phone' => $phone,
                'source' => $data['source'] ?? 'phone',
                'category' => CaseCategory::from($data['category']),
                'assigned_to' => $by->id,
                'last_message_at' => now(),
            ]);
            $case->log(EntryType::Message, trim($data['message']));
            $case->log(EntryType::Assigned, "Recorded and taken by {$by->name}.", $by);

            CaseOpened::dispatch($case, ['message' => trim($data['message'])]);

            return $case;
        });
    }
}
