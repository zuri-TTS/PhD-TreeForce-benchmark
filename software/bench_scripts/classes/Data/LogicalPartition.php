<?php
namespace Data;

abstract class LogicalPartition extends AbstractPartition
{

    protected function __construct(string $id)
    {
        parent::__construct($id);
    }

    public function isLogical(): bool
    {
        return true;
    }

    abstract function getLogicalRange(): array;

    abstract function getPhysicalParent(): PhysicalPartition;
}