<?php

declare(strict_types=1);

namespace Tests\Unit\Api\Model\Tool;

use PHPUnit\Framework\TestCase;
use Shelfwood\LMStudio\Api\Model\Tool\ToolCall;
use Shelfwood\LMStudio\Api\Model\Tool\ToolFunction;

class ToolCallTest extends TestCase
{
    public function test_can_create_tool_call(): void
    {
        $function = new ToolFunction('test_function', '{"param": "value"}');
        $call = new ToolCall('123', 'function', $function);

        $this->assertSame('123', $call->getId());
        $this->assertSame('function', $call->getType());
        $this->assertSame($function, $call->getFunction());
    }

    public function test_can_convert_to_array(): void
    {
        $function = new ToolFunction('test_function', '{"param": "value"}');
        $call = new ToolCall('123', 'function', $function);

        $array = $call->toArray();

        $this->assertSame([
            'id' => '123',
            'type' => 'function',
            'function' => [
                'name' => 'test_function',
                'arguments' => '{"param": "value"}',
            ],
        ], $array);
    }

    public function test_can_create_from_array(): void
    {
        $data = [
            'id' => '123',
            'type' => 'function',
            'function' => [
                'name' => 'test_function',
                'arguments' => '{"param": "value"}',
            ],
        ];

        $call = ToolCall::fromArray($data);

        $this->assertSame('123', $call->getId());
        $this->assertSame('function', $call->getType());
        $this->assertSame('test_function', $call->getFunction()->getName());
        $this->assertSame('{"param": "value"}', $call->getFunction()->getArguments());
    }
}
