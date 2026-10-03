<?php

namespace Modules\Access\Filament\Resources;

use App\Support\Filament\GatedByPermission;
use App\Support\Phone;
use App\Support\Scope;
use BackedEnum;
use Closure;
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
use Modules\Access\Filament\Resources\AccountResource\Pages\ManageAccounts;
use Modules\Access\Models\Account;
use Modules\Access\Models\Role;
use UnitEnum;

/** Mobile accounts. Staff add drivers and mates here by phone number. Riders sign themselves up in the app. */
class AccountResource extends Resource
{
    use GatedByPermission;

    protected static string $viewPermission = 'access.accounts.view';

    protected static string $managePermission = 'access.accounts.manage';

    protected static ?string $model = Account::class;

    protected static ?string $modelLabel = 'mobile account';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDevicePhoneMobile;

    protected static string|UnitEnum|null $navigationGroup = 'Access';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('phone')
                ->tel()->required()
                ->rule(fn () => function (string $attribute, mixed $value, Closure $fail) {
                    if (! Phone::isValid((string) $value)) {
                        $fail('Enter a valid Ghana phone number.');
                    }
                })
                ->disabledOn('edit'),
            TextInput::make('name')->maxLength(100),
            Select::make('role_ids')
                ->label('Roles')->multiple()
                ->helperText('One account can hold several, so a mate can also be a rider.')
                ->options(fn () => Role::query()->where('guard_name', Scope::App->value)->pluck('label', 'id')),
            Toggle::make('is_active')->label('Can log in')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('phone')->searchable(),
                TextColumn::make('name')->searchable(),
                TextColumn::make('roles.label')->badge(),
                IconColumn::make('is_active')->label('Can log in')->boolean(),
                TextColumn::make('last_login_at')->since()->placeholder('Never'),
            ])
            ->recordActions([
                EditAction::make()
                    ->mutateRecordDataUsing(fn (array $data, Account $record) => $data + ['role_ids' => $record->roles()->pluck('roles.id')->all()])
                    ->using(fn (Account $record, array $data) => static::save($record, $data)),
            ]);
    }

    public static function save(Account $account, array $data): Account
    {
        if (! $account->exists) {
            $account->phone = Phone::normalise($data['phone']);
        }

        $account->fill(collect($data)->only(['name', 'is_active'])->all())->save();
        $account->syncRoles(Role::query()->where('guard_name', Scope::App->value)->findMany($data['role_ids'] ?? []));

        return $account;
    }

    public static function getPages(): array
    {
        return ['index' => ManageAccounts::route('/')];
    }
}
