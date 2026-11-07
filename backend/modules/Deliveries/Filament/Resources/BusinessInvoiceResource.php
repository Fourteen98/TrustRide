<?php

namespace Modules\Deliveries\Filament\Resources;

use App\Support\Filament\Columns;
use App\Support\Filament\GatedByPermission;
use App\Support\Filament\Guarded;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Deliveries\Actions\MarkInvoicePaid;
use Modules\Deliveries\Filament\Resources\BusinessInvoiceResource\Pages\ManageBusinessInvoices;
use Modules\Deliveries\Models\BusinessInvoice;
use UnitEnum;

/** Each business's month, issued from Ox > Businesses. Paid outside the system, then marked paid here. */
class BusinessInvoiceResource extends Resource
{
    use GatedByPermission;

    protected static string $viewPermission = 'deliveries.invoices.manage';

    protected static string $managePermission = 'deliveries.invoices.manage';

    protected static ?string $model = BusinessInvoice::class;

    protected static ?string $modelLabel = 'invoice';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Ox';

    protected static ?int $navigationSort = 4;

    protected static function isReadOnly(): bool
    {
        return true;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('period', 'desc')
            ->columns([
                TextColumn::make('business.name')->label('Business')->searchable(),
                TextColumn::make('period')->label('Month')->date('F Y')->sortable(),
                TextColumn::make('deliveries_count')->label('Deliveries')
                    ->formatStateUsing(fn (BusinessInvoice $record) => $record->deliveries_count.($record->delivery_allowance ? " of {$record->delivery_allowance}" : '')),
                Columns::money('plan_fee')->label('Plan'),
                Columns::money('overage_amount')->label('Over')
                    ->description(fn (BusinessInvoice $record) => $record->overage_count ? "{$record->overage_count} deliveries" : null),
                Columns::money('live_map_fee')->label('Live map'),
                Columns::money('total')->label('Total'),
                IconColumn::make('paid_at')->label('Paid')->boolean()->state(fn (BusinessInvoice $record) => $record->isPaid()),
                TextColumn::make('payment_reference')->label('Reference')->placeholder('—'),
            ])
            ->recordActions([
                Action::make('paid')->label('Mark paid')->icon(Heroicon::OutlinedCheckCircle)->color('success')
                    ->visible(fn (BusinessInvoice $record) => ! $record->isPaid())
                    ->schema([TextInput::make('reference')->label('Payment reference')->required()->maxLength(100)])
                    ->action(Guarded::calls(fn (BusinessInvoice $record, array $data) => app(MarkInvoicePaid::class)->handle($record, Guarded::staff(), $data['reference'])))
                    ->successNotificationTitle('Marked paid'),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageBusinessInvoices::route('/')];
    }
}
