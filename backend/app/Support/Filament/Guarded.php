<?php

namespace App\Support\Filament;

use Closure;
use DomainException;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use ReflectionFunction;

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

    /**
     * Wraps what an admin button does, so every button refuses the same way.
     *
     *     ->action(Guarded::calls(fn (Driver $record, array $data) => app(SuspendDriver::class)->handle($record, $data['reason'])))
     *
     * The inner function may ask for $record, $data or $action by name, in any order, and leave out what it doesn't need.
     */
    public static function calls(Closure $callback): Closure
    {
        $wanted = array_map(fn ($parameter) => $parameter->getName(), (new ReflectionFunction($callback))->getParameters());

        return function (Action $action, ?Model $record = null, array $data = []) use ($callback, $wanted) {
            $available = ['action' => $action, 'record' => $record, 'data' => $data];

            return self::run($action, fn () => $callback(...array_map(fn (string $name) => $available[$name] ?? null, $wanted)));
        };
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
