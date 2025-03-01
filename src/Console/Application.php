<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Console;

use Shelfwood\LMStudio\Commands\Chat;
use Shelfwood\LMStudio\Commands\Sequence;
use Shelfwood\LMStudio\Config\LMStudioConfig;
use Shelfwood\LMStudio\LMStudio;
use Symfony\Component\Console\Application as SymfonyApplication;

class Application extends SymfonyApplication
{
    public function __construct()
    {
        parent::__construct('LMStudio CLI', '1.1.0');

        // Load configuration from environment variables or use defaults
        $baseUrl = getenv('LMSTUDIO_BASE_URL') ?: 'http://localhost:1234';
        $apiKey = getenv('LMSTUDIO_API_KEY') ?: 'lm-studio';
        $timeout = (int) (getenv('LMSTUDIO_TIMEOUT') ?: 30);
        $defaultModel = getenv('LMSTUDIO_DEFAULT_MODEL') ?: 'granite-3.1-8b-instruct';

        // Create config with explicit values
        $config = new LMStudioConfig(
            baseUrl: $baseUrl,
            apiKey: $apiKey,
            timeout: $timeout,
            defaultModel: $defaultModel
        );

        $lmstudio = new LMStudio($config);

        $this->add(new Sequence($lmstudio));
        $this->add(new Chat($lmstudio));
    }
}
