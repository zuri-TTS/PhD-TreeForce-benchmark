<?php
namespace Data;

abstract class PhysicalPartition implements IPartition
{

    private string $id;

    protected function __construct(string $id)
    {
        $this->id = $id;
    }

    function getID(): string
    {
        return $this->id;
    }

    final function getLogicalRange(): ?array
    {
        return null;
    }

    public function isLogical(): bool
    {
        return false;
    }

    abstract function getLogicalPartitioning(): ?IPartitioning;
}