<?php

namespace App\Api;

use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Modules\Access\Models\Staff;

/**
 * API documentation, generated from the code by Scramble.
 *
 *   /docs/api        the page to read
 *   /docs/api.json   the OpenAPI file, for generating the mobile app's API client or importing into Postman
 *
 * Routes behind `auth:app` are marked as needing a bearer token (see security_strategy in config/scramble.php).
 *
 * Nothing here is written by hand. To improve the docs, improve the code: validate the request,
 * return a JsonResource, and add a one-line comment above the controller method.
 */
class ApiDocsServiceProvider extends ServiceProvider
{
    /** URL prefix => heading in the docs. One heading per side of the app. */
    private const SIDES = [
        'api/auth' => 'Login',
        'api/rider' => 'Rider',
        'api/driver' => 'Driver and mate',
        'api/webhooks' => 'Webhooks',
    ];

    public function boot(): void
    {
        // Open on your own machine. Anywhere else, only staff who are logged in to the dashboard.
        Gate::define('viewApiDocs', fn (?Staff $staff = null) => $this->app->isLocal() || (bool) $staff?->is_active);

        Scramble::resolveTagsUsing(function (RouteInfo $routeInfo, Operation $operation) {
            foreach (self::SIDES as $prefix => $heading) {
                if (str_starts_with($routeInfo->route->uri(), $prefix)) {
                    return [$heading];
                }
            }

            return ['Other'];
        });
    }
}
