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

    'model' => env('LMSTUDIO_MODEL', 'qwen2.5-7b-instruct-1m'),
    'max_tokens' => env('LMSTUDIO_MAX_TOKENS', 1000),
    'temperature' => env('LMSTUDIO_TEMPERATURE', 0.7),
    'top_p' => env('LMSTUDIO_TOP_P', 1),

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
];
