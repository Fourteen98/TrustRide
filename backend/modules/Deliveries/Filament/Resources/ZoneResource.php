<?php

namespace Modules\Deliveries\Filament\Resources;

use App\Support\Filament\GatedByPermission;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Deliveries\Enums\DeliveryStatus;
use Modules\Deliveries\Filament\Resources\ZoneResource\Pages\ManageZones;
use Modules\Deliveries\Models\Zone;
use UnitEnum;

/** The areas Ox delivers to, each with its cut-off time and what customers are promised. */
class ZoneResource extends Resource
{
    use GatedByPermission;

    protected static string $viewPermission = 'deliveries.view';

    protected static string $managePermission = 'deliveries.setup';

    protected static ?string $model = Zone::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static string|UnitEnum|null $navigationGroup = 'Ox';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(60)->unique(ignoreRecord: true),
            TextInput::make('covers')->label('Places in it')->maxLength(200)
                ->helperText('So whoever enters a parcel can tell which zone an address is in. For example: Lapaz, Abeka, Tesano.'),
            TextInput::make('cutoff_time')->label('Cut-off')->required()->default('14:00')->regex('/^([01]\d|2[0-3]):[0-5]\d$/')
                ->helperText('24-hour time. Parcels in before it go on that day\'s run, later ones the next day.'),
            TextInput::make('promise')->label('Customers are promised')->required()->default('by 6pm')->maxLength(40)
                ->helperText('Read after "today" or "tomorrow": "today by 6pm".'),
            Toggle::make('is_active')->label('Taking parcels')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('covers')->label('Places')->placeholder('—')->wrap(),
                TextColumn::make('cutoff_time')->label('Cut-off'),
                TextColumn::make('promise')->label('Promise'),
                TextColumn::make('waiting')->label('Waiting today')
                    ->state(fn (Zone $record) => $record->deliveries()->whereDate('delivery_date', today())->where('status', DeliveryStatus::Pending)->count()),
                IconColumn::make('is_active')->label('Taking parcels')->boolean(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageZones::route('/')];
    }
}
