<?php

declare(strict_types=1);

return [
    'enabled' => env('BISUAL_MONITOR_ENABLED', true),

    'company_uuid' => env('BISUAL_MONITOR_COMPANY_UUID'),

    'company_product_uuid' => env('BISUAL_MONITOR_COMPANY_PRODUCT_UUID'),

    /*
    | API host only (scheme + host [+ port]). Path is fixed by the package.
    | Examples: https://api.bisual.net , http://localhost:8000
    */
    'host' => env('BISUAL_MONITOR_HOST', 'https://api.bisual.net'),

    'timeout_seconds' => (int) env('BISUAL_MONITOR_TIMEOUT_SECONDS', 5),

    'default_slug_prefix' => env('BISUAL_MONITOR_SLUG_PREFIX', ''),
];
