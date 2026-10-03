<?php

namespace Modules\Riders\Filament\Resources;

use App\Support\Filament\GatedByPermission;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\Riders\Enums\RiderStatus;
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

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('account.phone')->label('Phone')->searchable(),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (RiderStatus $state) => $state->label())
                    ->color(fn (RiderStatus $state) => $state === RiderStatus::Approved ? 'success' : 'gray'),
                TextColumn::make('created_at')->label('Joined')->date()->sortable(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageRiders::route('/')];
    }
}
