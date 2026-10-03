<?php

namespace Modules\Network\Filament\Resources;

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
use Modules\Network\Filament\Resources\StopResource\Pages\ManageStops;
use Modules\Network\Models\Stop;
use UnitEnum;

class StopResource extends Resource
{
    use GatedByPermission;

    protected static string $viewPermission = 'network.view';

    protected static string $managePermission = 'network.manage';

    protected static ?string $model = Stop::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static string|UnitEnum|null $navigationGroup = 'Network';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(100),
            TextInput::make('landmark')->helperText('What a rider looks for, e.g. "Opposite the Total station".')->maxLength(150),
            TextInput::make('area')->helperText('e.g. Bortianor, Ablekuma')->maxLength(100),
            Toggle::make('is_active')->label('In use')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('landmark')->placeholder('None'),
                TextColumn::make('area')->sortable()->placeholder('None'),
                TextColumn::make('routes_count')->counts('routes')->label('Routes'),
                IconColumn::make('is_active')->label('In use')->boolean(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageStops::route('/')];
    }
}
