<?php

namespace App\Support\Filament;

/** Choices for a select or filter, built the same way everywhere. */
final class Options
{
    /**
     * Every case of an enum as value => label. The enum must have a label() method.
     *
     * @param  class-string<\BackedEnum>  $enum
     * @return array<string, string>
     */
    public static function enum(string $enum): array
    {
        return collect($enum::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()])->all();
    }
}
