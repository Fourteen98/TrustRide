<?php

namespace App\Support;

/**
 * Where a role or permission applies. The value is also the auth guard name,
 * so a platform role can never hold an app permission.
 *
 * A third scope, Partner, is planned for outside companies. It is not built in v1.
 */
enum Scope: string
{
    case Platform = 'platform';
    case App = 'app';

    public function label(): string
    {
        return match ($this) {
            self::Platform => 'Platform (staff dashboard)',
            self::App => 'App (riders, drivers, mates)',
        };
    }
}
