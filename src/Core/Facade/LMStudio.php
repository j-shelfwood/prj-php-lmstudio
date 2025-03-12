<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Core\Facade;

use Illuminate\Support\Facades\Facade;
use Shelfwood\LMStudio\Api\Contract\LMStudioClientInterface;

/**
 * @method static LMStudioClientInterface lms()
 * @method static LMStudioClientInterface openai()
 * @method static \Shelfwood\LMStudio\LMStudio withBaseUrl(string $baseUrl)
 * @method static \Shelfwood\LMStudio\LMStudio withApiKey(string $apiKey)
 * @method static \Shelfwood\LMStudio\LMStudio withHeaders(array $headers)
 * @method static \Shelfwood\LMStudio\LMStudio withTimeout(int $timeout)
 *
 * @see \Shelfwood\LMStudio\LMStudio
 */
class LMStudio extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Shelfwood\LMStudio\LMStudio::class;
    }
}
