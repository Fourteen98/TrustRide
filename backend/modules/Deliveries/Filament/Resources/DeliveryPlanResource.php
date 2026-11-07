<?php

namespace Modules\Deliveries\Filament\Resources;

use App\Support\Filament\Columns;
use App\Support\Filament\GatedByPermission;
use App\Support\Filament\MoneyInput;
use App\Support\Filament\Options;
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
use Modules\Deliveries\Enums\LimitPolicy;
use Modules\Deliveries\Filament\Resources\DeliveryPlanResource\Pages\ManageDeliveryPlans;
use Modules\Deliveries\Models\DeliveryPlan;
use UnitEnum;

/** The monthly plans a business can be put on. Changing one never changes a business already on it. */
class DeliveryPlanResource extends Resource
{
    use GatedByPermission;

    protected static string $viewPermission = 'deliveries.view';

    protected static string $managePermission = 'deliveries.setup';

    protected static ?string $model = DeliveryPlan::class;

    protected static ?string $modelLabel = 'Ox plan';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Ox';

    protected static ?int $navigationSort = 6;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(40),
            MoneyInput::make('monthly_fee')->label('Monthly fee')->required(),
            TextInput::make('delivery_allowance')->label('Deliveries a month')->numeric()->integer()->minValue(1)
                ->helperText('Leave empty for a plan agreed business by business, like Custom.'),
            MoneyInput::make('overage_fee')->label('Each delivery over')->default(0),
            Select::make('at_limit')->label('At the limit')->required()->default(LimitPolicy::Overage->value)
                ->options(Options::enum(LimitPolicy::class)),
            Toggle::make('is_active')->label('Offered')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('monthly_fee')
            ->columns([
                TextColumn::make('name'),
                Columns::money('monthly_fee')->label('Monthly fee')->sortable(),
                TextColumn::make('delivery_allowance')->label('Deliveries a month')->placeholder('Agreed'),
                Columns::money('overage_fee')->label('Each over'),
                TextColumn::make('at_limit')->label('At the limit')->formatStateUsing(fn (LimitPolicy $state) => $state->label()),
                IconColumn::make('is_active')->label('Offered')->boolean(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageDeliveryPlans::route('/')];
    }
}
