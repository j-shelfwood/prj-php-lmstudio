<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | LMStudio API Configuration
    |--------------------------------------------------------------------------
    |
    | This file is for configuring the LMStudio API client. You can specify
    | the base URL, API key (not required for LMStudio but kept for OpenAI
    | compatibility), timeout, and additional headers.
    |
    */

    'base_url' => env('LMSTUDIO_BASE_URL', 'http://localhost:1234'),

    'api_key' => env('LMSTUDIO_API_KEY', 'lm-studio'),

    'timeout' => env('LMSTUDIO_TIMEOUT', 30),

    'headers' => [],
];
