<?php

namespace Modules\Support\Filament\Resources\CaseResource\Pages;

use App\Support\Filament\Guarded;
use App\Support\Filament\Options;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Modules\Access\Models\Staff;
use Modules\Support\Actions\AddNote;
use Modules\Support\Actions\AssignCase;
use Modules\Support\Actions\MarkIncident;
use Modules\Support\Actions\ReopenCase;
use Modules\Support\Actions\ReplyToCase;
use Modules\Support\Actions\ResolveCase;
use Modules\Support\Enums\Severity;
use Modules\Support\Filament\Resources\CaseResource;
use Modules\Support\Models\SupportCase;

/** One case: who, what about, the timeline, and every button that acts on it. */
class ViewCase extends ViewRecord
{
    protected static string $resource = CaseResource::class;

    public function getTitle(): string
    {
        return ($this->record->name ?? 'Someone').': '.$this->record->category->label();
    }

    protected function getHeaderActions(): array
    {
        $canReply = fn () => Guarded::can('support.queue.reply');
        $open = fn (SupportCase $record) => $canReply() && $record->isOpen();

        return [
            Action::make('reply')
                ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                ->visible($open)
                ->modalDescription('She sees this in the app and gets it as a message. Replying takes the case if nobody has.')
                ->schema([Textarea::make('body')->label('Reply')->required()->rows(4)->maxLength(600)])
                ->action($this->does(fn (SupportCase $record, array $data) => app(ReplyToCase::class)->handle($record, Guarded::staff(), $data['body'])))
                ->successNotificationTitle('Sent'),
            Action::make('note')
                ->label('Add a note')->icon(Heroicon::OutlinedPencilSquare)->color('gray')
                ->visible($canReply)
                ->modalDescription('For colleagues only. She never sees it.')
                ->schema([Textarea::make('body')->label('Note')->required()->rows(4)->maxLength(2000)])
                ->action($this->does(fn (SupportCase $record, array $data) => app(AddNote::class)->handle($record, Guarded::staff(), $data['body']))),
            Action::make('assign')
                ->label('Hand over')->icon(Heroicon::OutlinedArrowsRightLeft)->color('gray')
                ->visible($open)
                ->schema([
                    Select::make('staff_id')->label('To')->required()->searchable()
                        ->options(fn () => Staff::query()->orderBy('name')->pluck('name', 'id'))
                        ->default(fn () => Guarded::staff()->id),
                ])
                ->action($this->does(fn (SupportCase $record, array $data) => app(AssignCase::class)->handle(
                    $record, Staff::query()->findOrFail($data['staff_id']), Guarded::staff(),
                ))),
            Action::make('incident')
                ->label(fn (SupportCase $record) => $record->is_incident ? 'Change severity' : 'Mark as incident')
                ->icon(Heroicon::OutlinedExclamationTriangle)->color('danger')
                ->visible(fn () => Guarded::can('support.incidents.manage'))
                ->modalDescription('An incident is something Aveo must be able to account for later. The operator on call is texted, and it is kept even if she asks for her data to be deleted, without her name or number.')
                ->schema([Select::make('severity')->required()->options(Options::enum(Severity::class))])
                ->action($this->does(fn (SupportCase $record, array $data) => app(MarkIncident::class)->handle(
                    $record, Guarded::staff(), Severity::from($data['severity']),
                ))),
            Action::make('resolve')
                ->icon(Heroicon::OutlinedCheckCircle)->color('success')
                ->visible($open)
                ->schema([Textarea::make('resolution')->label('What was done about it')->required()->rows(3)->maxLength(2000)])
                ->action($this->does(fn (SupportCase $record, array $data) => app(ResolveCase::class)->handle($record, Guarded::staff(), $data['resolution'])))
                ->successNotificationTitle('Resolved'),
            Action::make('reopen')
                ->icon(Heroicon::OutlinedArrowUturnLeft)->color('gray')
                ->visible(fn (SupportCase $record) => $canReply() && ! $record->isOpen())
                ->requiresConfirmation()
                ->action($this->does(fn (SupportCase $record) => app(ReopenCase::class)->handle($record, Guarded::staff()))),
        ];
    }

    /** Runs a module Action the guarded way, then reloads the case so the timeline shows the new line. */
    private function does(\Closure $callback): \Closure
    {
        $guarded = Guarded::calls($callback);

        return function (Action $action, SupportCase $record, array $data = []) use ($guarded) {
            $guarded($action, $record, $data);

            $this->record = $record->fresh();
        };
    }
}
