<?php

namespace Modules\Support\Filament\Resources;

use App\Support\Filament\Columns;
use App\Support\Filament\GatedByPermission;
use App\Support\Filament\Guarded;
use App\Support\Filament\Options;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Support\Actions\AssignCase;
use Modules\Support\Enums\CaseCategory;
use Modules\Support\Enums\CaseStatus;
use Modules\Support\Enums\EntryType;
use Modules\Support\Filament\Resources\CaseResource\Pages\ManageCases;
use Modules\Support\Filament\Resources\CaseResource\Pages\ViewCase;
use Modules\Support\Models\CaseEntry;
use Modules\Support\Models\SupportCase;
use UnitEnum;

/**
 * The support queue. Everyone who wrote from the app or called, oldest unanswered first.
 * Opening a case shows its timeline, and everything that can be done to it.
 */
class CaseResource extends Resource
{
    use GatedByPermission;

    protected static string $viewPermission = 'support.queue.view';

    protected static string $managePermission = 'support.queue.reply';

    protected static ?string $model = SupportCase::class;

    protected static ?string $modelLabel = 'case';

    protected static ?string $navigationLabel = 'Queue';

    protected static ?string $slug = 'support';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLifebuoy;

    protected static string|UnitEnum|null $navigationGroup = 'Support';

    protected static ?int $navigationSort = 1;

    protected static function isReadOnly(): bool
    {
        return true;
    }

    /** Open cases nobody has taken yet. */
    public static function getNavigationBadge(): ?string
    {
        $waiting = SupportCase::query()->where('status', CaseStatus::Open)->whereNull('assigned_to')->count();

        return $waiting > 0 ? (string) $waiting : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function table(Table $table): Table
    {
        return $table
            // Open before resolved, then the one that has waited longest.
            ->modifyQueryUsing(fn (Builder $query) => $query->orderBy('status')->orderBy('last_message_at'))
            ->columns([
                IconColumn::make('is_incident')->label('')->icon(fn (bool $state) => $state ? Heroicon::ExclamationTriangle : null)->color('danger'),
                TextColumn::make('name')->label('Who')->placeholder('Erased')->searchable(),
                TextColumn::make('phone')->placeholder('Erased')->searchable(),
                TextColumn::make('category')->label('About')->formatStateUsing(fn (CaseCategory $state) => $state->label()),
                TextColumn::make('last_message_at')->label('Last wrote')->since(),
                TextColumn::make('assignee.name')->label('With')->placeholder('Nobody yet'),
                Columns::status('status', ['open' => 'warning', 'resolved' => 'success']),
                TextColumn::make('source')->label('Came by')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options(Options::enum(CaseStatus::class))->default(CaseStatus::Open->value),
                Filter::make('mine')->label('With me')->query(fn (Builder $query) => $query->where('assigned_to', Guarded::staff()->id)),
                TernaryFilter::make('is_incident')->label('Incidents'),
                SelectFilter::make('category')->options(Options::enum(CaseCategory::class)),
            ])
            ->recordActions([
                Action::make('take')
                    ->label('Take it')->icon(Heroicon::OutlinedHandRaised)
                    ->visible(fn (SupportCase $record) => Guarded::can('support.queue.reply') && $record->isOpen() && $record->assigned_to === null)
                    ->action(Guarded::calls(fn (SupportCase $record) => app(AssignCase::class)->handle($record, Guarded::staff(), Guarded::staff()))),
                ViewAction::make()->label('Open'),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('The case')->columns(3)->schema([
                TextEntry::make('name')->label('Who')->placeholder('Erased'),
                TextEntry::make('phone')->placeholder('Erased')->copyable(),
                TextEntry::make('role')->label('Uses the app as')->placeholder('No app account'),
                TextEntry::make('category')->label('About')->formatStateUsing(fn (CaseCategory $state) => $state->label()),
                TextEntry::make('status')->badge()->color(fn (CaseStatus $state) => $state === CaseStatus::Open ? 'warning' : 'success'),
                TextEntry::make('assignee.name')->label('With')->placeholder('Nobody yet'),
                TextEntry::make('context')->label('What she rides')->placeholder('Not known')->columnSpanFull(),
                TextEntry::make('severity')->label('Incident')->badge()->color('danger')
                    ->formatStateUsing(fn ($state) => $state?->label())->visible(fn (SupportCase $record) => $record->is_incident)->columnSpanFull(),
            ]),
            Section::make('Timeline')->schema([
                RepeatableEntry::make('entries')->hiddenLabel()->columns(4)->schema([
                    TextEntry::make('created_at')->hiddenLabel()->dateTime('j M, H:i'),
                    TextEntry::make('type')->hiddenLabel()->badge()
                        ->formatStateUsing(fn (EntryType $state, CaseEntry $record) => $state === EntryType::Message ? 'She wrote' : $state->label().($record->staff ? ', '.$record->staff->name : ''))
                        ->color(fn (EntryType $state) => match ($state) {
                            EntryType::Message => 'warning',
                            EntryType::Reply => 'success',
                            EntryType::Incident => 'danger',
                            default => 'gray',
                        }),
                    TextEntry::make('body')->hiddenLabel()->placeholder('')->columnSpan(2),
                ]),
            ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCases::route('/'),
            'view' => ViewCase::route('/{record}'),
        ];
    }
}
