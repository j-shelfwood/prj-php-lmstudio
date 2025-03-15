<?php

declare(strict_types=1);

namespace Tests\Unit\Api\Model\Tool;

use PHPUnit\Framework\TestCase;
use Shelfwood\LMStudio\Api\Model\Tool\ToolParameter;
use Shelfwood\LMStudio\Api\Model\Tool\ToolParameters;

class ToolParametersTest extends TestCase
{
    public function test_can_create_empty_tool_parameters(): void
    {
        $parameters = new ToolParameters;

        $array = $parameters->toArray();

        $this->assertSame([
            'type' => 'object',
            'properties' => [],
            'required' => [],
        ], $array);
    }

    public function test_can_add_properties(): void
    {
        $parameters = new ToolParameters;
        $parameter = new ToolParameter('string', 'A test parameter');

        $parameters->addProperty('test', $parameter);

        $array = $parameters->toArray();

        $this->assertSame([
            'type' => 'object',
            'properties' => [
                'test' => [
                    'type' => 'string',
                    'description' => 'A test parameter',
                ],
            ],
            'required' => [],
        ], $array);
    }

    public function test_can_add_required_properties(): void
    {
        $parameters = new ToolParameters;
        $parameter = new ToolParameter('string', 'A test parameter');

        $parameters
            ->addProperty('test', $parameter)
            ->addRequired('test');

        $array = $parameters->toArray();

        $this->assertSame([
            'type' => 'object',
            'properties' => [
                'test' => [
                    'type' => 'string',
                    'description' => 'A test parameter',
                ],
            ],
            'required' => ['test'],
        ], $array);
    }

    public function test_does_not_duplicate_required_properties(): void
    {
        $parameters = new ToolParameters;

        $parameters
            ->addRequired('test')
            ->addRequired('test');

        $array = $parameters->toArray();

        $this->assertSame([
            'type' => 'object',
            'properties' => [],
            'required' => ['test'],
        ], $array);
    }
}
