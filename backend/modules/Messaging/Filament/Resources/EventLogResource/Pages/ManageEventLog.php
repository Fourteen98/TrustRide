<?php

namespace Modules\Messaging\Filament\Resources\EventLogResource\Pages;

use Filament\Resources\Pages\ManageRecords;
use Modules\Messaging\Filament\Resources\EventLogResource;

class ManageEventLog extends ManageRecords
{
    protected static string $resource = EventLogResource::class;
}
