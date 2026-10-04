<?php

namespace Modules\Support\Events;

use App\Support\BaseDomainEvent;
use App\Support\Recipient;
use Modules\Support\Models\SupportCase;

/** Shared by the support events. $extra adds what one event needs on top, such as the reply text. */
abstract class CaseEvent extends BaseDomainEvent
{
    public int $caseId;

    /** @var array<string, mixed> */
    protected array $data;

    protected ?Recipient $person;

    public function __construct(SupportCase $case, array $extra = [])
    {
        $case->loadMissing('account');

        $this->caseId = $case->id;
        $this->data = [
            'case_id' => $case->id,
            'person_name' => $case->name ?? 'Someone',
            'person_phone' => $case->phone,
            'category' => $case->category->value,
            'category_label' => $case->category->label(),
            'context' => $case->context,
            'severity' => $case->severity?->value,
            ...$extra,
        ];
        $this->person = $case->phone
            ? new Recipient($case->role ?? 'rider', $case->phone, $case->account?->preferred_channel, $case->name)
            : null;
    }

    public function payload(): array
    {
        return $this->data;
    }
}
