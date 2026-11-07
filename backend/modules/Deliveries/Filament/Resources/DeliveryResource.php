<?php

namespace Modules\Deliveries\Filament\Resources;

use App\Support\Filament\Columns;
use App\Support\Filament\GatedByPermission;
use App\Support\Filament\Guarded;
use App\Support\Filament\Options;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Modules\Deliveries\Actions\CancelDelivery;
use Modules\Deliveries\Actions\ConfirmDelivery;
use Modules\Deliveries\Actions\RescheduleDelivery;
use Modules\Deliveries\Enums\DeliveryStatus;
use Modules\Deliveries\Filament\Resources\DeliveryResource\Pages\ManageDeliveries;
use Modules\Deliveries\Models\Delivery;
use Modules\Deliveries\Models\DeliveryRun;
use Modules\Deliveries\Models\Zone;
use UnitEnum;

/** Every parcel: where it is, and the few things an operator can do to one. */
class DeliveryResource extends Resource
{
    use GatedByPermission;

    protected static string $viewPermission = 'deliveries.view';

    protected static string $managePermission = 'deliveries.parcels.manage';

    protected static ?string $model = Delivery::class;

    protected static ?string $modelLabel = 'parcel';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static string|UnitEnum|null $navigationGroup = 'Ox';

    protected static ?int $navigationSort = 1;

    /** Parcels are added through "Send a parcel" and "Upload parcels", never edited as a form. */
    protected static function isReadOnly(): bool
    {
        return true;
    }

    /** The fields for one parcel, shared by every "Send a parcel" button. */
    public static function parcelFields(): array
    {
        return [
            Select::make('zone_id')->label('Zone')->required()
                ->options(fn () => Zone::query()->where('is_active', true)->orderBy('name')->get()
                    ->mapWithKeys(fn (Zone $zone) => [$zone->id => $zone->covers ? "{$zone->name} ({$zone->covers})" : $zone->name])),
            TextInput::make('customer_name')->label('Customer')->required()->maxLength(100),
            TextInput::make('customer_phone')->label('Customer\'s phone')->tel()->required()->maxLength(20)
                ->helperText('She is texted when to expect it, and a code to give the driver.'),
            TextInput::make('address')->required()->maxLength(250),
            TextInput::make('landmark')->maxLength(200)->helperText('What the driver should look for. Accra addresses need one.'),
            TextInput::make('reference')->label('Their order number')->maxLength(60),
            Textarea::make('notes')->rows(2)->maxLength(300),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('delivery_date', 'desc')
            ->columns([
                TextColumn::make('delivery_date')->label('Day')->date('D j M')->sortable(),
                TextColumn::make('zone.name')->label('Zone'),
                TextColumn::make('business.name')->label('Business')->searchable(),
                TextColumn::make('customer_name')->label('Customer')->searchable(),
                TextColumn::make('customer_phone')->label('Phone')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('address')->limit(40)->tooltip(fn (Delivery $record) => trim("{$record->address}. {$record->landmark}")),
                Columns::status('status', ['pending' => 'gray', 'assigned' => 'info', 'picked_up' => 'warning', 'delivered' => 'success', 'failed' => 'danger', 'cancelled' => 'gray']),
                TextColumn::make('run.driver.name')->label('Driver')->placeholder('—'),
                TextColumn::make('failure_reason')->label('Why not')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('reference')->label('Their ref')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('day')
                    ->schema([DatePicker::make('day')->default(today())])
                    ->query(fn (Builder $query, array $data) => $query->when($data['day'] ?? null, fn ($query, $day) => $query->whereDate('delivery_date', $day)))
                    ->indicateUsing(fn (array $data) => ($data['day'] ?? null) ? 'Day: '.Carbon::parse($data['day'])->format('D j M') : null),
                SelectFilter::make('status')->options(Options::enum(DeliveryStatus::class)),
                SelectFilter::make('zone')->relationship('zone', 'name'),
                SelectFilter::make('business')->relationship('business', 'name')->searchable(),
                SelectFilter::make('delivery_run_id')->label('Run')->relationship('run', 'id')
                    ->getOptionLabelFromRecordUsing(fn (DeliveryRun $record) => "{$record->zone->name}, {$record->date->format('D j M')}, {$record->driver->name}"),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('track')->label('Customer\'s page')->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                        ->url(fn (Delivery $record) => $record->trackingUrl(), shouldOpenInNewTab: true),
                    Action::make('reschedule')->label('Move to another day')->icon(Heroicon::OutlinedCalendarDays)
                        ->visible(fn (Delivery $record) => Guarded::can('deliveries.parcels.manage')
                            && in_array($record->status, [DeliveryStatus::Pending, DeliveryStatus::Assigned, DeliveryStatus::Failed], true))
                        ->schema([DatePicker::make('date')->required()->minDate(today())->default(today()->addDay())])
                        ->action(Guarded::calls(fn (Delivery $record, array $data) => app(RescheduleDelivery::class)->handle($record, Carbon::parse($data['date']))))
                        ->successNotificationTitle('Moved. The customer has been told the new day.'),
                    Action::make('confirm')->label('Confirm delivered without the code')->icon(Heroicon::OutlinedCheckBadge)
                        ->visible(fn (Delivery $record) => Guarded::can('deliveries.runs.manage') && $record->status === DeliveryStatus::PickedUp)
                        ->schema([Textarea::make('note')->label('How you know she has it')->required()->rows(2)->maxLength(250)])
                        ->action(Guarded::calls(fn (Delivery $record, array $data) => app(ConfirmDelivery::class)->handle($record, null, Guarded::staff(), $data['note'])))
                        ->successNotificationTitle('Marked delivered'),
                    Action::make('cancel')->icon(Heroicon::OutlinedXCircle)->color('danger')
                        ->visible(fn (Delivery $record) => Guarded::can('deliveries.parcels.manage')
                            && in_array($record->status, [DeliveryStatus::Pending, DeliveryStatus::Assigned], true))
                        ->requiresConfirmation()->modalDescription('Only before the driver collects it. A cancelled parcel doesn\'t count against the business\'s month.')
                        ->action(Guarded::calls(fn (Delivery $record) => app(CancelDelivery::class)->handle($record)))
                        ->successNotificationTitle('Cancelled'),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageDeliveries::route('/')];
    }
}
