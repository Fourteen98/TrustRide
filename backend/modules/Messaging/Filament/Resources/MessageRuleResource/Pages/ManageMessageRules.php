<?php

namespace Modules\Messaging\Filament\Resources\MessageRuleResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Modules\Messaging\Filament\Resources\MessageRuleResource;

class ManageMessageRules extends ManageRecords
{
    protected static string $resource = MessageRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
