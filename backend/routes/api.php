<?php

/*
 * Each side keeps its own route file next to its controllers.
 * Webhooks (calls from Hubtel's servers) are in app/Api/Webhooks.
 */
require app_path('Api/Auth/routes.php');
require app_path('Api/Rider/routes.php');
require app_path('Api/Driver/routes.php');
require app_path('Api/Support/routes.php');
require app_path('Api/Webhooks/routes.php');
