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

    /*
    |--------------------------------------------------------------------------
    | Default Model
    |--------------------------------------------------------------------------
    |
    | This is the default model that will be used when no model is specified.
    | For LM Studio, common models include 'qwen2.5-7b-instruct-1m' or others
    | available in your LM Studio installation.
    |
    */

    'default_model' => env('LMSTUDIO_DEFAULT_MODEL', 'qwen2.5-7b-instruct-1m'),

    /*
    |--------------------------------------------------------------------------
    | Connection Settings
    |--------------------------------------------------------------------------
    |
    | Configure connection-specific settings like connect timeout and
    | retry behavior for more reliable API interactions.
    |
    */

    'connect_timeout' => env('LMSTUDIO_CONNECT_TIMEOUT', 10),

    'idle_timeout' => env('LMSTUDIO_IDLE_TIMEOUT', 15),

    'max_retries' => env('LMSTUDIO_MAX_RETRIES', 3),

    /*
    |--------------------------------------------------------------------------
    | Health Check Configuration
    |--------------------------------------------------------------------------
    |
    | Configure health check behavior to verify the LMStudio server
    | is available before making requests.
    |
    */

    'health_check' => [
        'enabled' => env('LMSTUDIO_HEALTH_CHECK_ENABLED', true),
        'interval' => env('LMSTUDIO_HEALTH_CHECK_INTERVAL', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Debug Settings
    |--------------------------------------------------------------------------
    |
    | Configure debug options for troubleshooting API interactions.
    |
    */

    'debug' => [
        'enabled' => env('LMSTUDIO_DEBUG', false),
        'verbose' => env('LMSTUDIO_DEBUG_VERBOSE', false),
        'log_file' => env('LMSTUDIO_DEBUG_LOG', storage_path('logs/lmstudio.log')),
    ],
];
