<?php

namespace Modules\Access\Filament\Resources;

use App\Support\Filament\GatedByPermission;
use App\Support\Scope;
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
use Modules\Access\Filament\Resources\StaffResource\Pages\ManageStaff;
use Modules\Access\Models\Organisation;
use Modules\Access\Models\Role;
use Modules\Access\Models\Staff;
use UnitEnum;

class StaffResource extends Resource
{
    use GatedByPermission;

    protected static string $viewPermission = 'access.staff.view';

    protected static string $managePermission = 'access.staff.manage';

    protected static ?string $model = Staff::class;

    protected static ?string $modelLabel = 'staff member';

    protected static ?string $pluralModelLabel = 'staff';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Access';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(100),
            TextInput::make('email')->email()->required()->unique(ignoreRecord: true),
            TextInput::make('password')
                ->password()->revealable()->minLength(10)
                ->required(fn (string $operation) => $operation === 'create')
                ->dehydrated(fn (?string $state) => filled($state))
                ->helperText('Leave empty to keep the current password.'),
            Select::make('role_ids')
                ->label('Roles')->multiple()
                ->options(fn () => Role::query()->where('guard_name', Scope::Platform->value)->pluck('label', 'id')),
            Toggle::make('is_active')->label('Can log in')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('roles.label')->badge(),
                IconColumn::make('is_active')->label('Can log in')->boolean(),
            ])
            ->recordActions([
                EditAction::make()
                    ->mutateRecordDataUsing(fn (array $data, Staff $record) => $data + ['role_ids' => $record->roles()->pluck('roles.id')->all()])
                    ->using(fn (Staff $record, array $data) => static::save($record, $data)),
            ]);
    }

    public static function save(Staff $staff, array $data): Staff
    {
        $staff->organisation_id ??= Organisation::platformOwner()->id;
        $staff->fill(collect($data)->only(['name', 'email', 'password', 'is_active'])->all())->save();
        $staff->syncRoles(Role::query()->where('guard_name', Scope::Platform->value)->findMany($data['role_ids'] ?? []));

        return $staff;
    }

    public static function getPages(): array
    {
        return ['index' => ManageStaff::route('/')];
    }
}
