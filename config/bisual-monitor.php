<?php

declare(strict_types=1);

return [
    'enabled' => env('BISUAL_MONITOR_ENABLED', true),

    'company_uuid' => env('BISUAL_MONITOR_COMPANY_UUID'),

    'company_product_uuid' => env('BISUAL_MONITOR_COMPANY_PRODUCT_UUID'),

    'endpoint' => env(
        'BISUAL_MONITOR_ENDPOINT',
        'https://api.bisual.net/api/v1/companies/{company_uuid}/company-products/{company_product_uuid}/status-monitors/exec'
    ),

    'timeout_seconds' => (int) env('BISUAL_MONITOR_TIMEOUT_SECONDS', 5),

    'default_slug_prefix' => env('BISUAL_MONITOR_SLUG_PREFIX', ''),
];
