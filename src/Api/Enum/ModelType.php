<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Api\Enum;

enum ModelType: string
{
    case LLM = 'llm';
    case VLM = 'vlm';
    case EMBEDDINGS = 'embeddings';
}