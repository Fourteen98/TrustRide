<?php

namespace App\Support\Filament;

use App\Support\Money;
use Filament\Forms\Components\TextInput;

/** A cedis input for a column stored in pesewas. */
final class MoneyInput
{
    public static function make(string $name): TextInput
    {
        return TextInput::make($name)
            ->numeric()
            ->minValue(0)
            ->prefix('GHS')
            ->formatStateUsing(fn ($state) => $state === null ? null : Money::toCedis((int) $state))
            ->dehydrateStateUsing(fn ($state) => Money::toPesewas($state ?? 0));
    }
}
