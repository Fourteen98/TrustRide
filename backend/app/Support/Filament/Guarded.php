<?php

namespace App\Support\Filament;

use Closure;
use DomainException;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;

/**
 * Helpers for admin screens that call module Actions.
 *
 * A module says "no" by throwing a DomainException. In the console that becomes a red
 * notice with the module's own sentence, and the form stays open so nothing typed is lost.
 */
final class Guarded
{
    public static function run(Action $action, Closure $callback): mixed
    {
        try {
            return $callback();
        } catch (DomainException $e) {
            Notification::make()->danger()->title($e->getMessage())->persistent()->send();

            $action->halt();
        }

        return null;
    }

    public static function can(string $permission): bool
    {
        return (bool) Filament::auth()->user()?->can($permission);
    }

    /** The staff member using the console. */
    public static function staff(): mixed
    {
        return Filament::auth()->user();
    }
}
