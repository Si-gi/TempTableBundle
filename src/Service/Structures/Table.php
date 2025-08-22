<?php
declare(strict_types=1);

namespace Sigi\TempTableBundle\Service\Structures;

use Doctrine\Common\Collections\ArrayCollection;

class Table
{
    private ArrayCollection $columns;

    public function __construct(private string $name, private string $prefix)
    {
        $this->columns = new ArrayCollection();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPrefix(): string
    {
        return $this->prefix;
    }

    public function getFullName(): string
    {
        return $this->prefix.$this->name;
    }

    public function addColumn(Column $column): self
    {
        if (false === $this->columns->contains($column)) {
            $this->columns->add($column);
            $column->setIndex($this->columns->indexOf($column));
        }

        return $this;
    }

    public function getColumns(): ArrayCollection
    {
        return $this->columns;
    }

    public function removeColumn(Column $column): self
    {
        $this->columns->removeElement($column);

        return $this;
    }

    public function getColumnsByName(string $name): ?Column
    {
        foreach ($this->columns as $column) {
            if ($column->getName() === $name) {
                return $column;
            }
        }

        return null;
    }

    public function getColumnByIndex(int $index): ?Column
    {
        return $this->columns->get($index);
    }
}
