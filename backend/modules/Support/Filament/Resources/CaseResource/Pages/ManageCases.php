<?php

namespace Modules\Support\Filament\Resources\CaseResource\Pages;

use App\Support\Filament\Guarded;
use App\Support\Filament\Options;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ManageRecords;
use Modules\Support\Actions\OpenCase;
use Modules\Support\Enums\CaseCategory;
use Modules\Support\Filament\Resources\CaseResource;

class ManageCases extends ManageRecords
{
    protected static string $resource = CaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('record')
                ->label('Record a case')
                ->visible(fn () => Guarded::can('support.queue.reply'))
                ->modalDescription('For someone who called or sent a WhatsApp. If her number has an app account, she will see the case in the app.')
                ->schema([
                    TextInput::make('name')->required()->maxLength(80),
                    TextInput::make('phone')->required()->tel(),
                    Select::make('source')->label('Came by')->required()->default('phone')->options(['phone' => 'Phone call', 'whatsapp' => 'WhatsApp']),
                    Select::make('category')->label('About')->required()->options(Options::enum(CaseCategory::class)),
                    Textarea::make('message')->label('What she said')->required()->rows(4)->maxLength(2000),
                ])
                ->action(Guarded::calls(fn (array $data) => app(OpenCase::class)->handle(Guarded::staff(), $data)))
                ->successNotificationTitle('Recorded'),
        ];
    }
}
