<?php

namespace Modules\Subscriptions\Filament\Resources;

use App\Support\Filament\GatedByPermission;
use App\Support\Filament\MoneyInput;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Subscriptions\Enums\NoShowPolicy;
use Modules\Subscriptions\Filament\Resources\PlanResource\Pages\ManagePlans;
use Modules\Subscriptions\Models\Plan;
use UnitEnum;

class PlanResource extends Resource
{
    use GatedByPermission;

    protected static string $viewPermission = 'subscriptions.view';

    protected static string $managePermission = 'subscriptions.plans.manage';

    protected static ?string $model = Plan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static string|UnitEnum|null $navigationGroup = 'Subscriptions';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(60),
            MoneyInput::make('price')->required(),
            TextInput::make('duration_days')->label('Length in days')->numeric()->required()->minValue(1)->maxValue(366),
            Select::make('trips_per_day')->label('What it covers')->required()->default(1)
                ->options([1 => 'One trip a day (one seat)', 2 => 'There and back (two seats)']),
            Select::make('no_show_policy')->label('When she doesn\'t ride')->required()->default(NoShowPolicy::CreditWithNotice->value)
                ->options(collect(NoShowPolicy::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()])),
            TextInput::make('notice_cutoff_minutes')->label('Notice needed, in minutes before departure')
                ->helperText('720 is 12 hours. For a 6:30am van, that is 6:30pm the evening before.')
                ->numeric()->required()->minValue(0)->default(720),
            Toggle::make('is_active')->label('On sale')->default(true)
                ->helperText('Changing a plan never changes subscriptions already bought.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('duration_days')
            ->columns([
                TextColumn::make('name')->sortable(),
                TextColumn::make('price')->formatStateUsing(fn (int $state) => Money::format($state))->sortable(),
                TextColumn::make('duration_days')->label('Days')->sortable(),
                TextColumn::make('trips_per_day')->label('Covers')->formatStateUsing(fn (int $state) => $state === 1 ? 'One trip a day' : 'There and back'),
                TextColumn::make('no_show_policy')->label('No-shows')->formatStateUsing(fn (NoShowPolicy $state) => $state->label()),
                IconColumn::make('is_active')->label('On sale')->boolean(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManagePlans::route('/')];
    }
}
