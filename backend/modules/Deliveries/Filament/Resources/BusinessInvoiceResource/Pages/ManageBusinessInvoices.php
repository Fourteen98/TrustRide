<?php

namespace Modules\Deliveries\Filament\Resources\BusinessInvoiceResource\Pages;

use Filament\Resources\Pages\ManageRecords;
use Modules\Deliveries\Filament\Resources\BusinessInvoiceResource;

class ManageBusinessInvoices extends ManageRecords
{
    protected static string $resource = BusinessInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
