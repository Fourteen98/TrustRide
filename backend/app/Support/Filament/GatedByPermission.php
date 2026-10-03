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
 */
trait GatedByPermission
{
    /** Every check Filament makes on a resource comes through here, including the ones behind its buttons. */
    public static function getAuthorizationResponse(string|UnitEnum $action, ?Model $record = null): Response
    {
        $action = $action instanceof UnitEnum ? $action->name : $action;

        $allowed = in_array($action, ['viewAny', 'view'], true)
            ? static::allows(static::$viewPermission) || static::allows(static::$managePermission)
            : static::allows(static::$managePermission);

        return $allowed ? Response::allow() : Response::deny();
    }

    protected static function allows(string $permission): bool
    {
        return (bool) Filament::auth()->user()?->can($permission);
    }
}
