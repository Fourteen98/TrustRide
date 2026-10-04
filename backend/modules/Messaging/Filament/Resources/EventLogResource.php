<?php

namespace Modules\Messaging\Filament\Resources;

use App\Support\Filament\GatedByPermission;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Messaging\Filament\Resources\EventLogResource\Pages\ManageEventLog;
use Modules\Messaging\Models\EventLog;
use UnitEnum;

/** Everything that has happened, as the modules reported it. Read-only. Useful when asking "why did she get that message?" */
class EventLogResource extends Resource
{
    use GatedByPermission;

    protected static string $viewPermission = 'messaging.log.view';

    protected static string $managePermission = 'messaging.rules.manage';

    protected static ?string $model = EventLog::class;

    protected static ?string $modelLabel = 'event';

    protected static ?string $navigationLabel = 'Events';

    protected static ?string $slug = 'events';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedListBullet;

    protected static string|UnitEnum|null $navigationGroup = 'Messaging';

    protected static ?int $navigationSort = 3;

    protected static function isReadOnly(): bool
    {
        return true;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('occurred_at')->label('When')->dateTime('j M, H:i')->sortable(),
                TextColumn::make('name')->label('What')->badge()->searchable(),
                TextColumn::make('about')->label('About')
                    ->state(fn (EventLog $record) => collect($record->payload)->only(['rider_name', 'driver_name', 'route', 'stop', 'plan', 'message'])->filter()->join(' · '))
                    ->wrap(),
                TextColumn::make('messages_count')->counts('messages')->label('Messages'),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageEventLog::route('/')];
    }
}
