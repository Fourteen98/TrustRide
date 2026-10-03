<?php

namespace Modules\Access\Filament\Resources;

use App\Support\Filament\GatedByPermission;
use App\Support\Scope;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Access\Actions\SetRoleAccess;
use Modules\Access\Filament\Resources\RoleResource\Pages\ManageRoles;
use Modules\Access\Models\Permission;
use Modules\Access\Models\PermissionGroup;
use Modules\Access\Models\Role;
use UnitEnum;

class RoleResource extends Resource
{
    use GatedByPermission;

    protected static string $viewPermission = 'access.roles.view';

    protected static string $managePermission = 'access.roles.manage';

    protected static ?string $model = Role::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|UnitEnum|null $navigationGroup = 'Access';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('label')->required()->maxLength(60),
            TextInput::make('name')
                ->helperText('Used in code. Lowercase, no spaces. Can\'t be changed later.')
                ->required()->alphaDash()->maxLength(40)
                ->disabledOn('edit'),
            Select::make('guard_name')
                ->label('Scope')
                ->options(collect(Scope::cases())->mapWithKeys(fn (Scope $scope) => [$scope->value => $scope->label()]))
                ->required()->live()
                ->disabledOn('edit'),
            Select::make('screens')
                ->label('Mobile screens this role opens')
                ->options(['rider' => 'Rider screens', 'driver' => 'Driver screens', 'mate' => 'Mate screens'])
                ->visible(fn (Get $get) => $get('guard_name') === Scope::App->value),
            CheckboxList::make('group_ids')
                ->label('Permission groups')
                ->helperText('The role keeps the group. Permissions added to a group later reach this role.')
                ->options(fn (Get $get) => PermissionGroup::query()
                    ->where('guard_name', $get('guard_name'))->orderBy('module')->orderBy('label')
                    ->get()->mapWithKeys(fn ($group) => [$group->id => "{$group->module}: {$group->label}"]))
                ->columns(2)->columnSpanFull(),
            CheckboxList::make('permission_ids')
                ->label('Single permissions')
                ->helperText('Extra permissions for this role only, outside any group.')
                ->options(fn (Get $get) => Permission::query()
                    ->where('guard_name', $get('guard_name'))->orderBy('name')
                    ->get()->mapWithKeys(fn ($permission) => [$permission->id => "{$permission->label} ({$permission->name})"]))
                ->searchable()->columns(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('label')->searchable()->sortable(),
                TextColumn::make('guard_name')->label('Scope')->badge()->sortable(),
                TextColumn::make('groups_count')->counts('groups')->label('Groups'),
                TextColumn::make('permissions_count')->counts('permissions')->label('Permissions'),
            ])
            ->recordActions([
                EditAction::make()
                    ->mutateRecordDataUsing(fn (array $data, Role $record) => $data + [
                        'group_ids' => $record->groups()->pluck('permission_groups.id')->all(),
                        'permission_ids' => $record->tickedPermissions()->pluck('permissions.id')->all(),
                    ])
                    ->using(fn (Role $record, array $data) => static::save($record, $data)),
                DeleteAction::make()->hidden(fn (Role $record) => $record->is_system),
            ]);
    }

    /** Every change to a role's access goes through the SetRoleAccess action. */
    public static function save(Role $role, array $data): Role
    {
        $role->fill(collect($data)->only(['label', 'name', 'guard_name', 'screens'])->all())->save();

        return app(SetRoleAccess::class)->handle($role, $data['group_ids'] ?? [], $data['permission_ids'] ?? []);
    }

    public static function getPages(): array
    {
        return ['index' => ManageRoles::route('/')];
    }
}
