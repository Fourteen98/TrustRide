<?php

/*
 * Each side keeps its own route file next to its controllers.
 * Webhooks (Hubtel, WhatsApp) will join this list as app/Api/Webhooks/routes.php.
 */
require app_path('Api/Auth/routes.php');
require app_path('Api/Rider/routes.php');
require app_path('Api/Driver/routes.php');
