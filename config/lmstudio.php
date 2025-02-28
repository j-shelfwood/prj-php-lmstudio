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
    | For LM Studio, common models include 'granite-3.1-8b-instruct' or others
    | available in your LM Studio installation.
    |
    */

    'default_model' => env('LMSTUDIO_DEFAULT_MODEL', 'granite-3.1-8b-instruct'),
];
