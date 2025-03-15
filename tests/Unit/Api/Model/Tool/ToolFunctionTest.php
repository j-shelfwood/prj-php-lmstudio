<?php

declare(strict_types=1);

namespace Tests\Unit\Api\Model\Tool;

use PHPUnit\Framework\TestCase;
use Shelfwood\LMStudio\Api\Model\Tool\ToolFunction;

class ToolFunctionTest extends TestCase
{
    public function test_can_create_tool_function(): void
    {
        $function = new ToolFunction('test_function', '{"param": "value"}');

        $this->assertSame('test_function', $function->getName());
        $this->assertSame('{"param": "value"}', $function->getArguments());
    }

    public function test_can_convert_to_array(): void
    {
        $function = new ToolFunction('test_function', '{"param": "value"}');

        $array = $function->toArray();

        $this->assertSame([
            'name' => 'test_function',
            'arguments' => '{"param": "value"}',
        ], $array);
    }

    public function test_can_create_from_array(): void
    {
        $data = [
            'name' => 'test_function',
            'arguments' => '{"param": "value"}',
        ];

        $function = ToolFunction::fromArray($data);

        $this->assertSame('test_function', $function->getName());
        $this->assertSame('{"param": "value"}', $function->getArguments());
    }
}
