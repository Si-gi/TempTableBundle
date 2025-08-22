<?php
declare(strict_types=1);

namespace Sigi\TempTableBundle\Service\Structures;

class Column
{
    private int $index;
    public string $name;
    public array $values = [];

    public function __construct(string $name, public ?string $type = null)
    {
        $this->name = $this->sanitizeName($name);
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(?string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getIndex(): int
    {
        return $this->index;
    }

    public function setIndex(int $index): static
    {
        $this->index = $index;

        return $this;
    }

    public function addValue(mixed $value): self
    {
        if (\is_array($value)) {
            $value = json_encode($value); // stringify
        }
        $this->values[] = $value;

        return $this;
    }

    public function getValues(): array
    {
        return $this->values;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * sanitize column name for PostgreSQL
     */
    private function sanitizeName(string $name): string
    {
        // Replace special characters with underscores
        $name = preg_replace('/[^a-zA-Z0-9_]/', '_', $name);
        // start by a letter if necessary
        if (preg_match('/^[0-9]/', $name)) {
            $name = 'col_'.$name;
        }

        return strtolower($name);
    }
}
