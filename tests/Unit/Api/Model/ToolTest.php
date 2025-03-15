<?php

declare(strict_types=1);

namespace Tests\Unit\Api\Model;

use PHPUnit\Framework\TestCase;
use Shelfwood\LMStudio\Api\Enum\ToolType;
use Shelfwood\LMStudio\Api\Model\Tool;
use Shelfwood\LMStudio\Api\Model\Tool\ToolDefinition;
use Shelfwood\LMStudio\Api\Model\Tool\ToolParameter;
use Shelfwood\LMStudio\Api\Model\Tool\ToolParameters;

class ToolTest extends TestCase
{
    public function test_tool_can_be_created_with_type_and_function(): void
    {
        $parameters = new ToolParameters;
        $parameters->addProperty('test', new ToolParameter('string', 'A test parameter'));
        $definition = new ToolDefinition('test_tool', 'A test tool', $parameters);

        $tool = new Tool(ToolType::FUNCTION, $definition);

        $this->assertSame(ToolType::FUNCTION, $tool->getType());
        $this->assertSame($definition, $tool->getDefinition());
    }

    public function test_tool_can_be_converted_to_array(): void
    {
        $parameters = new ToolParameters;
        $parameters->addProperty('test', new ToolParameter('string', 'A test parameter'));
        $definition = new ToolDefinition('test_tool', 'A test tool', $parameters);

        $tool = new Tool(ToolType::FUNCTION, $definition);

        $array = $tool->toArray();

        $this->assertSame([
            'type' => 'function',
            'function' => [
                'name' => 'test_tool',
                'description' => 'A test tool',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'test' => [
                            'type' => 'string',
                            'description' => 'A test parameter',
                        ],
                    ],
                    'required' => [],
                ],
            ],
        ], $array);
    }

    public function test_tool_can_be_created_from_array(): void
    {
        $array = [
            'type' => 'function',
            'function' => [
                'name' => 'test_tool',
                'description' => 'A test tool',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'test' => [
                            'type' => 'string',
                            'description' => 'A test parameter',
                        ],
                    ],
                    'required' => [],
                ],
            ],
        ];

        $tool = Tool::fromArray($array);

        $this->assertSame(ToolType::FUNCTION, $tool->getType());
        $this->assertSame('test_tool', $tool->getDefinition()->getName());
        $this->assertSame('A test tool', $tool->getDefinition()->getDescription());
    }
}
