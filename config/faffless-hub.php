<?php

return [
    'url' => env('FAFFLESS_HUB_URL', 'https://communication.faffless.ai'),
    'api_key' => env('FAFFLESS_HUB_API_KEY'),
    'tenant_api_key' => env('FAFFLESS_HUB_TENANT_API_KEY'),
    'timeout' => env('FAFFLESS_HUB_TIMEOUT', 10),
];
