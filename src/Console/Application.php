<?php

namespace Shelfwood\LMStudio\Console;

use Shelfwood\LMStudio\Commands\Chat;
use Shelfwood\LMStudio\Commands\Models;
use Shelfwood\LMStudio\Commands\Tools;
use Shelfwood\LMStudio\LMStudio;
use Symfony\Component\Console\Application as BaseApplication;

class Application extends BaseApplication
{
    protected LMStudio $lmstudio;

    public function __construct(string $name = 'LMStudio CLI', string $version = '1.0.0')
    {
        parent::__construct($name, $version);

        // Initialize LMStudio client
        $config = require __DIR__.'/../../config/lmstudio.php';
        $this->lmstudio = new LMStudio($config);

        // Register commands
        $this->addCommands([
            new Chat($this->lmstudio),
            new Models($this->lmstudio),
            new Tools($this->lmstudio),
        ]);
    }

    public function getLMStudio(): LMStudio
    {
        return $this->lmstudio;
    }
}
