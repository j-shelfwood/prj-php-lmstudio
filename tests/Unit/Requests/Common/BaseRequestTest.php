<?php

declare(strict_types=1);

use Shelfwood\LMStudio\Requests\Common\BaseRequest;

// Create a concrete implementation of the abstract BaseRequest for testing
class ConcreteBaseRequest extends BaseRequest
{
    private array $data;

    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    public function jsonSerialize(): array
    {
        return $this->data;
    }
}

describe('BaseRequest', function (): void {
    it('implements RequestInterface', function (): void {
        $request = new ConcreteBaseRequest;

        expect($request)->toBeInstanceOf(\Shelfwood\LMStudio\Requests\Common\RequestInterface::class);
        expect($request)->toBeInstanceOf(\JsonSerializable::class);
    });

    it('converts to array via toArray method', function (): void {
        $data = ['key' => 'value', 'nested' => ['foo' => 'bar']];
        $request = new ConcreteBaseRequest($data);

        $array = $request->toArray();

        expect($array)->toBe($data);
    });

    it('toArray method returns same as jsonSerialize', function (): void {
        $data = ['model' => 'gpt-4', 'temperature' => 0.7];
        $request = new ConcreteBaseRequest($data);

        expect($request->toArray())->toBe($request->jsonSerialize());
    });
});
