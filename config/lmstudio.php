<?php

return [
    /*
    |--------------------------------------------------------------------------
    | LMStudio API Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration settings for connecting to your local
    | LMStudio API server. Adjust these settings based on your setup.
    |
    */

    'host' => env('LMSTUDIO_HOST', 'localhost'),
    'port' => env('LMSTUDIO_PORT', 1234),

    /*
    |--------------------------------------------------------------------------
    | Connection Settings
    |--------------------------------------------------------------------------
    |
    | Configure the connection behavior for API requests
    |
    */
    'timeout' => env('LMSTUDIO_TIMEOUT', 60),
    'retry_attempts' => env('LMSTUDIO_RETRY_ATTEMPTS', 3),
    'retry_delay' => env('LMSTUDIO_RETRY_DELAY', 100),

    /*
    |--------------------------------------------------------------------------
    | Default Model Settings
    |--------------------------------------------------------------------------
    |
    | Configure the default model and settings to use for requests
    |
    */
    'default_model' => env('LMSTUDIO_DEFAULT_MODEL'),
    'temperature' => env('LMSTUDIO_TEMPERATURE', 0.7),
    'max_tokens' => env('LMSTUDIO_MAX_TOKENS', -1),
];