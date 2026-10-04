<?php

namespace App\Support\Filament;

use Filament\Facades\Filament;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Ties an admin screen to two permissions: one to see it, one to change things.
 * A screen the user can't see is left out of the menu, and the route itself refuses them.
 * Without the second permission the add, edit and delete buttons are not there.
 *
 * The resource sets: protected static string $viewPermission, $managePermission.
 * A list-only screen also overrides isReadOnly().
 */
trait GatedByPermission
{
    /** Every check Filament makes on a resource comes through here, including the ones behind its buttons. */
    public static function getAuthorizationResponse(string|UnitEnum $action, ?Model $record = null): Response
    {
        $action = $action instanceof UnitEnum ? $action->name : $action;

        $allowed = in_array($action, ['viewAny', 'view'], true)
            ? static::allows(static::$viewPermission) || static::allows(static::$managePermission)
            : ! static::isReadOnly() && static::allows(static::$managePermission);

        return $allowed ? Response::allow() : Response::deny();
    }

    /**
     * Override to return true on a screen that only lists things. Nothing can then be added, edited
     * or deleted through Filament's own buttons. The screen's custom actions decide for themselves.
     */
    protected static function isReadOnly(): bool
    {
        return false;
    }

    protected static function allows(string $permission): bool
    {
        return (bool) Filament::auth()->user()?->can($permission);
    }
}
