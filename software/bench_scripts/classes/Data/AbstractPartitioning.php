<?php
namespace Data;

abstract class AbstractPartitioning implements IPartitioning
{

    private \DataSet $ds;

    private array $partitions;

    protected function __construct(\DataSet $ds, array $partitions)
    {
        $this->ds = $ds;
        $this->partitions = $partitions;
    }
}