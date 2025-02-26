<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Shelfwood\LMStudio\Support\ChatBuilder chat()
 * @method static array listModels()
 * @method static array getModel(string $model)
 * @method static array createChatCompletion(array $parameters)
 * @method static array createEmbeddings(string $model, string|array $input)
 *
 * @see \Shelfwood\LMStudio\Endpoints\LMStudio
 */
class LMStudio extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'lmstudio';
    }
}
