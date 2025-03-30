<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Api\Model\Tool;

class ToolParameters
{
    private string $type = 'object';

    /**
     * @param  array<string, ToolParameter>  $properties
     * @param  string[]  $required
     */
    public function __construct(
        /** @var array<string, ToolParameter> */
        private array $properties = [],
        /** @var string[] */
        private array $required = []
    ) {}

    public function addProperty(string $name, ToolParameter $parameter): self
    {
        $this->properties[$name] = $parameter;

        return $this;
    }

    public function addRequired(string $name): self
    {
        if (! in_array($name, $this->required, true)) {
            $this->required[] = $name;
        }

        return $this;
    }

    public function toArray(): array
    {
        $propertiesObject = [];

        foreach ($this->properties as $name => $parameter) {
            $propertiesObject[$name] = $parameter->toArray();
        }

        return [
            'type' => $this->type,
            'properties' => (object) $propertiesObject,
            'required' => $this->required,
        ];
    }
}
