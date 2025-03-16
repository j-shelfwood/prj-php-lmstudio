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

    'api_key' => env('LMSTUDIO_API_KEY', ''),

    'timeout' => env('LMSTUDIO_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Default Model
    |--------------------------------------------------------------------------
    |
    | This is the default model that will be used when no model is specified.
    | For LM Studio, common models include 'qwen2.5-7b-instruct' or others
    | available in your LM Studio installation.
    |
    */

    'model' => env('LMSTUDIO_MODEL', 'qwen2.5-7b-instruct'),
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

    /*
    |--------------------------------------------------------------------------
    | LMStudio Base URL
    |--------------------------------------------------------------------------
    |
    | This is the base URL for the LMStudio API. By default, it points to the
    | LMStudio API server, but you can change it to point to a local server
    | or a different endpoint.
    |
    */

    'default_model' => env('LMSTUDIO_DEFAULT_MODEL', 'gpt-4'),

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how tool execution jobs are queued.
    |
    */
    'queue' => [
        // The queue connection to use for tool execution jobs
        'connection' => env('LMSTUDIO_QUEUE_CONNECTION', null),

        // The queue name to use for tool execution jobs
        'queue' => env('LMSTUDIO_QUEUE', 'default'),

        // The number of seconds the job can run before timing out
        'timeout' => env('LMSTUDIO_QUEUE_TIMEOUT', 60),

        // The number of times to attempt the job before failing
        'tries' => env('LMSTUDIO_QUEUE_TRIES', 3),

        // Whether to queue tool execution jobs by default
        'queue_tools_by_default' => env('LMSTUDIO_QUEUE_TOOLS_BY_DEFAULT', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Streaming Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how streaming responses are handled.
    |
    */
    'streaming' => [
        // Whether to enable streaming by default
        'enabled_by_default' => env('LMSTUDIO_STREAMING_ENABLED_BY_DEFAULT', false),
    ],
];
