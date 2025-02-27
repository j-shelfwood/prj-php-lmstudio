<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Console;

use Shelfwood\LMStudio\Commands\Sequence;
use Shelfwood\LMStudio\Config\LMStudioConfig;
use Shelfwood\LMStudio\LMStudio;
use Symfony\Component\Console\Application as SymfonyApplication;

class Application extends SymfonyApplication
{
    public function __construct()
    {
        parent::__construct('LMStudio CLI', '1.0.0');

        // Load configuration from environment variables or use defaults
        $baseUrl = getenv('LMSTUDIO_BASE_URL') ?: 'http://localhost:1234';
        $apiKey = getenv('LMSTUDIO_API_KEY') ?: 'lm-studio';
        $timeout = (int) (getenv('LMSTUDIO_TIMEOUT') ?: 30);

        // Create config with explicit values
        $config = new LMStudioConfig(
            baseUrl: $baseUrl,
            apiKey: $apiKey,
            timeout: $timeout
        );

        $lmstudio = new LMStudio($config);

        $this->add(new Sequence($lmstudio));
    }
}
