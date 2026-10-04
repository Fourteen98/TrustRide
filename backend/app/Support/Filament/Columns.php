<?php

namespace App\Support\Filament;

use App\Support\Money;
use BackedEnum;
use Filament\Tables\Columns\TextColumn;

/** Table columns that show the same kind of thing the same way on every screen. */
final class Columns
{
    /** A column stored in pesewas, shown as "GHS 60". */
    public static function money(string $name): TextColumn
    {
        return TextColumn::make($name)->formatStateUsing(fn ($state) => $state === null ? null : Money::format((int) $state));
    }

    /**
     * A status shown as a coloured badge. Works for an enum with a label() method or a plain string.
     *
     * @param  array<string, string>  $colours  status value => Filament colour (success, warning, danger, gray)
     */
    public static function status(string $name, array $colours = []): TextColumn
    {
        $value = fn ($state) => $state instanceof BackedEnum ? $state->value : (string) $state;

        return TextColumn::make($name)
            ->badge()
            ->formatStateUsing(fn ($state) => $state instanceof BackedEnum && method_exists($state, 'label') ? $state->label() : ucfirst(str_replace('_', ' ', $value($state))))
            ->color(fn ($state) => $colours[$value($state)] ?? 'gray');
    }
}
