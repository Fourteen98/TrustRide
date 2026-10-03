<?php

namespace Modules\Network\Filament\Resources;

use App\Support\Filament\GatedByPermission;
use App\Support\Filament\Guarded;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Modules\Network\Actions\SetRouteStops;
use Modules\Network\Filament\Resources\RouteResource\Pages\ManageRoutes;
use Modules\Network\Models\Route;
use Modules\Network\Models\Stop;
use UnitEnum;

class RouteResource extends Resource
{
    use GatedByPermission;

    protected static string $viewPermission = 'network.view';

    protected static string $managePermission = 'network.manage';

    protected static ?string $model = Route::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static string|UnitEnum|null $navigationGroup = 'Network';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(100)->placeholder('Bortianor to Circle'),
            TextInput::make('destination')->required()->maxLength(100)->placeholder('Circle'),
            TextInput::make('origin_area')->label('Pickup area')->maxLength(100)->placeholder('Bortianor–Barrier'),
            Toggle::make('is_active')->label('In use')->default(true),
            Repeater::make('stop_rows')
                ->label('Stops, in the order the van reaches them')
                ->helperText('The last stop is the destination. Drag to reorder.')
                ->schema([
                    Select::make('stop_id')->label('Stop')->required()->searchable()
                        ->options(fn () => Stop::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')),
                    TextInput::make('minutes_from_start')->label('Minutes from the first stop')->numeric()->minValue(0)->default(0),
                ])
                ->columns(2)->minItems(2)->reorderable()->columnSpanFull()->addActionLabel('Add a stop'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('destination')->sortable(),
                TextColumn::make('stops.name')->label('Stops')->listWithLineBreaks()->limitList(6),
                IconColumn::make('is_active')->label('In use')->boolean(),
            ])
            ->recordActions([
                EditAction::make()
                    ->mutateRecordDataUsing(fn (array $data, Route $record) => $data + [
                        'stop_rows' => $record->stops->map(fn (Stop $stop) => [
                            'stop_id' => $stop->id,
                            'minutes_from_start' => $stop->pivot->minutes_from_start,
                        ])->all(),
                    ])
                    ->using(fn (Action $action, Route $record, array $data) => static::save($action, $record, $data)),
            ]);
    }

    public static function save(Action $action, Route $route, array $data): ?Route
    {
        return Guarded::run($action, fn () => DB::transaction(function () use ($route, $data) {
            $route->fill(collect($data)->except('stop_rows')->all())->save();

            return app(SetRouteStops::class)->handle($route, $data['stop_rows'] ?? []);
        }));
    }

    public static function getPages(): array
    {
        return ['index' => ManageRoutes::route('/')];
    }
}
