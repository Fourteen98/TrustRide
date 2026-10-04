<?php

namespace Modules\Messaging\Enums;

enum MessageStatus: string
{
    case Queued = 'queued';
    case Sent = 'sent';
    case Failed = 'failed';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
