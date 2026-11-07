<?php

namespace Modules\Deliveries\Filament\Resources;

use App\Support\Filament\Columns;
use App\Support\Filament\GatedByPermission;
use App\Support\Filament\Guarded;
use App\Support\Filament\MoneyInput;
use App\Support\Filament\Options;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Modules\Deliveries\Actions\CreateDelivery;
use Modules\Deliveries\Actions\ImportDeliveries;
use Modules\Deliveries\Actions\IssueInvoice;
use Modules\Deliveries\Actions\SaveBusiness;
use Modules\Deliveries\Enums\BusinessStatus;
use Modules\Deliveries\Enums\LimitPolicy;
use Modules\Deliveries\Filament\Resources\BusinessResource\Pages\ManageBusinesses;
use Modules\Deliveries\Models\Business;
use Modules\Deliveries\Models\DeliveryPlan;
use UnitEnum;

/** The shops and sellers on Ox: their plan, what they have used this month, and sending their parcels. */
class BusinessResource extends Resource
{
    use GatedByPermission;

    protected static string $viewPermission = 'deliveries.view';

    protected static string $managePermission = 'deliveries.setup';

    protected static ?string $model = Business::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static string|UnitEnum|null $navigationGroup = 'Ox';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(100),
            TextInput::make('contact_name')->label('Contact person')->required()->maxLength(100),
            TextInput::make('phone')->tel()->required()->maxLength(20),
            TextInput::make('email')->email()->maxLength(150),
            TextInput::make('pickup_address')->label('Where parcels are collected')->required()->maxLength(200),
            TextInput::make('pickup_landmark')->label('Landmark')->maxLength(200),
            Select::make('delivery_plan_id')->label('Plan')->live()
                ->options(fn () => DeliveryPlan::query()->where('is_active', true)->orderBy('monthly_fee')->pluck('name', 'id'))
                ->afterStateUpdated(function (?string $state, Set $set) {
                    if ($plan = DeliveryPlan::find($state)) {
                        $set('monthly_fee', Money::toCedis($plan->monthly_fee));
                        $set('delivery_allowance', $plan->delivery_allowance);
                        $set('overage_fee', Money::toCedis($plan->overage_fee));
                        $set('at_limit', $plan->at_limit->value);
                    }
                })
                ->helperText('Choosing a plan fills in its numbers. Change any of them for this business alone, as for Custom.'),
            MoneyInput::make('monthly_fee')->label('Monthly fee')->required(),
            TextInput::make('delivery_allowance')->label('Deliveries a month')->numeric()->integer()->minValue(1)
                ->helperText('Empty means no limit.'),
            MoneyInput::make('overage_fee')->label('Each delivery over')->default(0),
            Select::make('at_limit')->label('At the limit')->required()->default(LimitPolicy::Overage->value)
                ->options(Options::enum(LimitPolicy::class)),
            Toggle::make('live_map')->label('Live map add-on')
                ->helperText('Adds '.Money::format((int) config('deliveries.live_map_fee')).' a month to its invoice. The map itself comes after the pilot.'),
            Select::make('status')->required()->default(BusinessStatus::Active->value)->options(Options::enum(BusinessStatus::class)),
            Textarea::make('notes')->rows(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->searchable()->sortable()->description(fn (Business $record) => $record->contact_name.', '.$record->phone),
                TextColumn::make('plan.name')->label('Plan')->placeholder('Agreed'),
                Columns::money('monthly_fee')->label('A month'),
                TextColumn::make('used')->label('This month')
                    ->state(fn (Business $record) => $record->deliveriesIn(now()).($record->delivery_allowance ? " of {$record->delivery_allowance}" : '')),
                IconColumn::make('live_map')->label('Live map')->boolean(),
                Columns::status('status', ['active' => 'success', 'paused' => 'gray']),
            ])
            ->recordActions([
                Action::make('send')->label('Send a parcel')->icon(Heroicon::OutlinedPlus)
                    ->visible(fn (Business $record) => Guarded::can('deliveries.parcels.manage') && $record->isActive())
                    ->schema(DeliveryResource::parcelFields())
                    ->action(Guarded::calls(fn (Business $record, array $data) => app(CreateDelivery::class)->handle($record, $data, Guarded::staff())))
                    ->successNotificationTitle('Parcel added. The customer has been told when to expect it.'),
                ActionGroup::make([
                    EditAction::make()->using(Guarded::calls(fn (Business $record, array $data) => app(SaveBusiness::class)->handle($record, $data))),
                    Action::make('upload')->label('Upload parcels')->icon(Heroicon::OutlinedArrowUpTray)
                        ->visible(fn (Business $record) => Guarded::can('deliveries.parcels.manage') && $record->isActive())
                        ->modalDescription('A spreadsheet saved as CSV. First row: customer_name, customer_phone, zone, address, and if you like landmark, notes, reference. If any row is wrong, nothing is added and the wrong rows are listed.')
                        ->schema([FileUpload::make('file')->required()->storeFiles(false)->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel'])->maxSize(1024)])
                        ->action(Guarded::calls(function (Business $record, array $data, Action $action) {
                            $file = $data['file'];
                            $contents = $file instanceof UploadedFile ? $file->get() : Storage::get($file);
                            $added = app(ImportDeliveries::class)->handle($record, (string) $contents, Guarded::staff());
                            $action->successNotificationTitle("{$added} parcels added. Each customer has been told when to expect hers.");
                        }))
                        ->successNotificationTitle('Parcels added'),
                    Action::make('invoice')->label('Invoice a month')->icon(Heroicon::OutlinedDocumentText)
                        ->visible(fn () => Guarded::can('deliveries.invoices.manage'))
                        ->schema([DatePicker::make('month')->label('Any day in the month')->required()->default(now()->subMonthNoOverflow()->startOfMonth())])
                        ->action(Guarded::calls(fn (Business $record, array $data) => app(IssueInvoice::class)->handle($record, Carbon::parse($data['month']))))
                        ->successNotificationTitle('Invoice ready under Ox > Invoices'),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageBusinesses::route('/')];
    }
}
