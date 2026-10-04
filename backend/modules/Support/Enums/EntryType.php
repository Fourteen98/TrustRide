<?php

namespace Modules\Support\Enums;

enum EntryType: string
{
    case Message = 'message'; // from the person
    case Reply = 'reply'; // staff to the person
    case Note = 'note'; // staff only
    case Assigned = 'assigned';
    case Incident = 'incident';
    case Resolved = 'resolved';
    case Reopened = 'reopened';

    public function label(): string
    {
        return match ($this) {
            self::Message => 'She wrote',
            self::Reply => 'Reply',
            self::Note => 'Note (staff only)',
            self::Assigned => 'Assigned',
            self::Incident => 'Marked as incident',
            self::Resolved => 'Resolved',
            self::Reopened => 'Reopened',
        };
    }

    /** Only what was said between her and us is shown in the app. Notes and housekeeping are not. */
    public function isVisibleToPerson(): bool
    {
        return in_array($this, [self::Message, self::Reply], true);
    }
}
