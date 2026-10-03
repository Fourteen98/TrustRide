<?php

use App\Admin\AdminPanelProvider;
use App\Api\ApiDocsServiceProvider;
use App\Providers\AppServiceProvider;
use Modules\Access\Providers\AccessServiceProvider;
use Modules\Drivers\Providers\DriversServiceProvider;
use Modules\Messaging\Providers\MessagingServiceProvider;
use Modules\Network\Providers\NetworkServiceProvider;
use Modules\Payments\Providers\PaymentsServiceProvider;
use Modules\Riders\Providers\RidersServiceProvider;
use Modules\Subscriptions\Providers\SubscriptionsServiceProvider;
use Modules\Support\Providers\SupportServiceProvider;
use Modules\Trips\Providers\TripsServiceProvider;

return [
    AppServiceProvider::class, // first: it creates the module registry

    // Modules: the business, split by domain.
    AccessServiceProvider::class,
    NetworkServiceProvider::class,
    RidersServiceProvider::class,
    DriversServiceProvider::class,
    SubscriptionsServiceProvider::class,
    TripsServiceProvider::class,
    SupportServiceProvider::class,
    PaymentsServiceProvider::class,
    MessagingServiceProvider::class,

    // Sides: who is using it.
    AdminPanelProvider::class,
    ApiDocsServiceProvider::class,
];
