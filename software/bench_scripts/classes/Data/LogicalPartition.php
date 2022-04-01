<?php
namespace Data;

abstract class LogicalPartition implements IPartition
{

    private string $id;

    protected function __construct(string $id)
    {
        $this->id = $id;
    }

    public function getID(): string
    {
        return $this->id;
    }

    public function isLogical(): bool
    {
        return true;
    }

    abstract function getLogicalRange(): array;
}