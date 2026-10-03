<?php

namespace Modules\Drivers\Filament;

use App\Support\Filament\Guarded;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Support\Icons\Heroicon;
use Modules\Drivers\Actions\OverrideVettingCheck;
use Modules\Drivers\Actions\RecordVettingCheck;
use Modules\Drivers\Enums\VettingResult;
use Modules\Drivers\Models\Driver;
use Modules\Drivers\Models\Vehicle;
use Modules\Drivers\Models\VettingCheck;
use Modules\Drivers\Models\VettingItem;

/** The vetting checklist and the override, shared by the driver and vehicle screens. */
final class VettingActions
{
    /** One row per checklist item: passed, failed or not checked yet, with notes. */
    public static function checklist(): Action
    {
        return Action::make('vetting')
            ->label('Vetting')
            ->icon(Heroicon::OutlinedClipboardDocumentList)
            ->visible(fn () => Guarded::can('drivers.vet'))
            ->modalHeading(fn (Driver|Vehicle $record) => 'Vetting: '.($record instanceof Driver ? $record->name : $record->registration_number))
            ->schema(fn (Driver|Vehicle $record) => VettingItem::query()->for($record)->get()->flatMap(fn (VettingItem $item) => [
                Select::make("result_{$item->id}")
                    ->label($item->label.($item->is_required ? '' : ' (optional)'))
                    ->options(['passed' => 'Passed', 'failed' => 'Failed'])
                    ->placeholder('Not checked yet'),
                Textarea::make("notes_{$item->id}")->label('Notes')->rows(1),
            ])->all())
            ->fillForm(fn (Driver|Vehicle $record) => $record->vettingChecks->flatMap(fn (VettingCheck $check) => [
                "result_{$check->vetting_item_id}" => $check->result->value,
                "notes_{$check->vetting_item_id}" => $check->notes,
            ])->all())
            ->action(function (Action $action, Driver|Vehicle $record, array $data) {
                $existing = $record->vettingChecks->keyBy('vetting_item_id');

                Guarded::run($action, function () use ($record, $data, $existing) {
                    foreach (VettingItem::query()->for($record)->get() as $item) {
                        $result = $data["result_{$item->id}"] ?? null;
                        $notes = $data["notes_{$item->id}"] ?? null;
                        $before = $existing->get($item->id);

                        // Leave an item alone if nothing about it changed, so an override is not wiped by accident.
                        if ($result === null || ($before && $before->result->value === $result && $before->notes === $notes)) {
                            continue;
                        }

                        app(RecordVettingCheck::class)->handle($record, $item, VettingResult::from($result), Guarded::staff(), $notes);
                    }
                });
            })
            ->successNotificationTitle('Vetting saved');
    }

    /** Lets a failed item through. Records who and why. */
    public static function override(): Action
    {
        return Action::make('override')
            ->label('Override')
            ->icon(Heroicon::OutlinedShieldExclamation)
            ->color('warning')
            ->visible(fn (Driver|Vehicle $record) => Guarded::can('drivers.vetting.override') && self::failed($record)->isNotEmpty())
            ->modalHeading('Override a failed check')
            ->modalDescription('Your name and the reason are kept on the record.')
            ->schema(fn (Driver|Vehicle $record) => [
                Select::make('check_id')->label('Failed check')->required()
                    ->options(self::failed($record)->mapWithKeys(fn (VettingCheck $check) => [$check->id => $check->item->label])),
                Textarea::make('reason')->label('Why is this acceptable?')->required()->rows(3),
            ])
            ->action(fn (Action $action, Driver|Vehicle $record, array $data) => Guarded::run($action, fn () => app(OverrideVettingCheck::class)->handle(
                $record->vettingChecks()->findOrFail($data['check_id']), Guarded::staff(), $data['reason'],
            )))
            ->successNotificationTitle('Override recorded');
    }

    private static function failed(Driver|Vehicle $record)
    {
        return $record->vettingChecks()->with('item')->get()
            ->filter(fn (VettingCheck $check) => $check->result === VettingResult::Failed && ! $check->isOverridden());
    }
}
