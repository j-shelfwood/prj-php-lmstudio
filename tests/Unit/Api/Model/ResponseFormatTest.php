<?php

declare(strict_types=1);

use Shelfwood\LMStudio\Api\Enum\ResponseFormatType;
use Shelfwood\LMStudio\Api\Exception\ValidationException;
use Shelfwood\LMStudio\Api\Model\ResponseFormat;

describe('ResponseFormat', function (): void {
    test('can create response format with text type', function (): void {
        $responseFormat = new ResponseFormat(ResponseFormatType::TEXT);

        expect($responseFormat->type)->toBe(ResponseFormatType::TEXT);
        expect($responseFormat->jsonSchema)->toBeNull();
    });

    test('can create response format with json schema type', function (): void {
        $jsonSchema = [
            'name' => 'joke_response',
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'joke' => [
                        'type' => 'string',
                    ],
                ],
                'required' => ['joke'],
            ],
        ];

        $responseFormat = new ResponseFormat(ResponseFormatType::JSON_SCHEMA, $jsonSchema);

        expect($responseFormat->type)->toBe(ResponseFormatType::JSON_SCHEMA);
        expect($responseFormat->jsonSchema)->toBe($jsonSchema);
    });

    test('can create response format from array with text type', function (): void {
        $data = [
            'type' => 'text',
        ];

        $responseFormat = ResponseFormat::fromArray($data);

        expect($responseFormat->type)->toBe(ResponseFormatType::TEXT);
        expect($responseFormat->jsonSchema)->toBeNull();
    });

    test('can create response format from array with json schema type', function (): void {
        $jsonSchema = [
            'name' => 'joke_response',
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'joke' => [
                        'type' => 'string',
                    ],
                ],
                'required' => ['joke'],
            ],
        ];

        $data = [
            'type' => 'json_schema',
            'json_schema' => $jsonSchema,
        ];

        $responseFormat = ResponseFormat::fromArray($data);

        expect($responseFormat->type)->toBe(ResponseFormatType::JSON_SCHEMA);
        expect($responseFormat->jsonSchema)->toBe($jsonSchema);
    });

    test('can convert response format to array with text type', function (): void {
        $responseFormat = new ResponseFormat(ResponseFormatType::TEXT);

        $array = $responseFormat->toArray();

        expect($array)->toBe([
            'type' => 'text',
        ]);
    });

    test('can convert response format to array with json schema type', function (): void {
        $jsonSchema = [
            'name' => 'joke_response',
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'joke' => [
                        'type' => 'string',
                    ],
                ],
                'required' => ['joke'],
            ],
        ];

        $responseFormat = new ResponseFormat(ResponseFormatType::JSON_SCHEMA, $jsonSchema);

        $array = $responseFormat->toArray();

        expect($array)->toBe([
            'type' => 'json_schema',
            'json_schema' => $jsonSchema,
        ]);
    });

    test('throws validation exception when json schema is missing', function (): void {
        expect(fn () => new ResponseFormat(ResponseFormatType::JSON_SCHEMA))
            ->toThrow(ValidationException::class, 'JSON schema is required when type is json_schema');
    });
});
