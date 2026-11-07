<?php

namespace Modules\Drivers\Enums;

/** Whose van it is. Today it changes nothing in how the van runs; it is for the console and the reports. */
enum VehicleOwnership: string
{
    case Own = 'own';
    case Partner = 'partner';

    public function label(): string
    {
        return match ($this) {
            self::Own => 'Aveo',
            self::Partner => 'Partner',
        };
    }
}
