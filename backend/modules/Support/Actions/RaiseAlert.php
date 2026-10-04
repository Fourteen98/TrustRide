<?php

namespace Modules\Support\Actions;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Access\Models\Account;
use Modules\Support\Enums\CaseCategory;
use Modules\Support\Enums\CaseStatus;
use Modules\Support\Enums\EntryType;
use Modules\Support\Enums\Severity;
use Modules\Support\Events\CaseOpened;
use Modules\Support\Events\IncidentRaised;
use Modules\Support\Models\SupportCase;

/**
 * Someone writes to TrustRide from the app.
 *
 * A person has one open case at a time. Her first message opens it. Anything she writes
 * while it is open is added to it. Once it is resolved, her next message opens a new one.
 *
 * $who is what the side taking the message knows about her: name, role, and what she rides
 * (context). Support itself knows nothing about seats or runs.
 */
class RaiseAlert
{
    /** @param array{name?: ?string, role?: ?string, context?: ?string} $who */
    public function handle(Account $account, string $message, ?CaseCategory $category = null, array $who = []): SupportCase
    {
        $message = trim($message);

        if ($message === '') {
            throw new DomainException('Tell us what happened.');
        }

        return DB::transaction(function () use ($account, $message, $category, $who) {
            $case = SupportCase::query()
                ->where('account_id', $account->id)
                ->where('status', CaseStatus::Open)
                ->lockForUpdate()
                ->first();

            if ($case) {
                $case->log(EntryType::Message, $message);
                $case->update(['last_message_at' => now()]);

                return $case;
            }

            $category ??= CaseCategory::Other;

            $case = SupportCase::query()->create([
                'account_id' => $account->id,
                'name' => $who['name'] ?? $account->name,
                'phone' => $account->phone,
                'role' => $who['role'] ?? null,
                'context' => isset($who['context']) ? str($who['context'])->limit(480)->toString() : null,
                'category' => $category,
                'is_incident' => $category->isUrgent(),
                'severity' => $category->isUrgent() ? Severity::High : null,
                'last_message_at' => now(),
            ]);
            $case->log(EntryType::Message, $message);

            CaseOpened::dispatch($case, ['message' => $message]);

            if ($case->is_incident) {
                $case->log(EntryType::Incident, 'Automatically, because of what it is about.');
                IncidentRaised::dispatch($case, ['message' => $message]);
            }

            return $case;
        });
    }
}
