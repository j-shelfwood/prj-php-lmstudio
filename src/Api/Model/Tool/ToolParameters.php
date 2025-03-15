<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Api\Model\Tool;

class ToolParameters
{
    private string $type = 'object';

    /** @var array<string, ToolParameter> */
    private array $properties;

    /** @var string[] */
    private array $required;

    /**
     * @param  array<string, ToolParameter>  $properties
     * @param  string[]  $required
     */
    public function __construct(array $properties = [], array $required = [])
    {
        $this->properties = $properties;
        $this->required = $required;
    }

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
        return [
            'type' => $this->type,
            'properties' => array_map(fn (ToolParameter $p) => $p->toArray(), $this->properties),
            'required' => $this->required,
        ];
    }
}
