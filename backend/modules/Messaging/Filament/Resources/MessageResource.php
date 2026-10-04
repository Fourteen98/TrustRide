<?php

namespace Modules\Messaging\Filament\Resources;

use App\Support\Filament\Columns;
use App\Support\Filament\GatedByPermission;
use App\Support\Filament\Guarded;
use App\Support\Filament\Options;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Messaging\Actions\SendMessage;
use Modules\Messaging\Enums\MessageStatus;
use Modules\Messaging\Filament\Resources\MessageResource\Pages\ManageMessages;
use Modules\Messaging\Models\Message;
use UnitEnum;

/** The delivery log: what was sent to whom, on which channel, and what failed. */
class MessageResource extends Resource
{
    use GatedByPermission;

    protected static string $viewPermission = 'messaging.log.view';

    protected static string $managePermission = 'messaging.broadcast';

    protected static ?string $model = Message::class;

    protected static ?string $modelLabel = 'message';

    protected static ?string $pluralModelLabel = 'delivery log';

    protected static ?string $navigationLabel = 'Delivery log';

    protected static ?string $slug = 'messages';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPaperAirplane;

    protected static string|UnitEnum|null $navigationGroup = 'Messaging';

    protected static ?int $navigationSort = 1;

    protected static function isReadOnly(): bool
    {
        return true;
    }

    public static function getNavigationBadge(): ?string
    {
        $failed = Message::query()->where('status', MessageStatus::Failed)->count();

        return $failed > 0 ? (string) $failed : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('When')->since()->sortable(),
                TextColumn::make('recipient_name')->label('To')->placeholder('Unknown')->searchable(),
                TextColumn::make('recipient_phone')->label('Phone')->searchable(),
                TextColumn::make('body')->label('Message')->wrap()->limit(110),
                TextColumn::make('channel')->formatStateUsing(fn (?string $state) => $state ? strtoupper($state) : null)->placeholder('None'),
                Columns::status('status', ['sent' => 'success', 'failed' => 'danger', 'queued' => 'warning']),
                TextColumn::make('why')->label('If it failed')->state(fn (Message $record) => $record->status === MessageStatus::Failed ? $record->lastError() : null)->placeholder(''),
                TextColumn::make('event.name')->label('Because of')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options(Options::enum(MessageStatus::class)),
            ])
            ->recordActions([
                Action::make('retry')
                    ->label('Try again')->icon(Heroicon::OutlinedArrowPath)
                    ->visible(fn (Message $record) => Guarded::can('messaging.broadcast') && $record->status === MessageStatus::Failed)
                    ->action(fn (Message $record) => app(SendMessage::class)->handle($record))
                    ->successNotificationTitle('Tried again'),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageMessages::route('/')];
    }
}
