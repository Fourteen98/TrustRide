<?php

namespace Modules\Riders\Filament\Resources;

use App\Support\Filament\Columns;
use App\Support\Filament\GatedByPermission;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Riders\Filament\Resources\RiderResource\Pages\ManageRiders;
use Modules\Riders\Models\Rider;
use UnitEnum;

/** A list to look riders up. Riders create themselves in the app, so nothing is added or edited here. */
class RiderResource extends Resource
{
    use GatedByPermission;

    protected static string $viewPermission = 'riders.view';

    protected static string $managePermission = 'riders.review';

    protected static ?string $model = Rider::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Riders';

    protected static ?int $navigationSort = 2;

    protected static function isReadOnly(): bool
    {
        return true;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('account.phone')->label('Phone')->searchable(),
                Columns::status('status', ['approved' => 'success']),
                TextColumn::make('created_at')->label('Joined')->date()->sortable(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageRiders::route('/')];
    }
}
