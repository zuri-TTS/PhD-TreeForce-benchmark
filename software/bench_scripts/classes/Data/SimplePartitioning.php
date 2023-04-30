<?php
namespace Data;

final class SimplePartitioning
{

    private function __construct()
    {
        throw new \AssertionError();
    }

    public static function simplePartition(\DataSet $ds): IPartition
    {
        return new class($ds->group(), IPartition::NO_PID) extends AbstractPartition {};
    }

    public static function empty(\DataSet $ds): IPartitioning
    {
        return new class($ds) implements IPartitioning {

            private \DataSet $ds;

            function __construct(\DataSet $ds)
            {
                $this->ds = $ds;
            }

            function getID(): string
            {
                return '';
            }

            function getDataSet(): \DataSet
            {
                return $this->ds;
            }

            function getPartitions(): array
            {
                return [];
            }
        };
    }

    public static function create(\DataSet $ds): IPartitioning
    {
        return new class($ds) implements IPartitioning {

            private IPartition $part;

            private string $id;

            function __construct(\DataSet $ds)
            {
                $this->part = SimplePartitioning::simplePartition($ds);
                $this->id = $ds->group_partitioning();
            }

            function getID(): string
            {
                return '';
            }

            function getDataSet(): \DataSet
            {
                return $this->part->getDataSet();
            }

            function getPartitions(): array
            {
                return [
                    $this->part
                ];
            }
        };
    }

    public static function builder(\DataSet $ds): IPartitioningBuilder
    {
        return new class($ds) implements IPartitioningBuilder {

            private \DataSet $ds;

            private IPartitioning $partitioning;

            function __construct(\DataSet $ds)
            {
                $this->ds = $ds;
                $this->partitioning = SimplePartitioning::create($ds);
            }

            function getDataSet(): \DataSet
            {
                return $this->ds;
            }

            function load(): IPartitioning
            {
                return $this->partitioning;
            }

            function save(): IPartitioning
            {
                return $this->partitioning;
            }

            function getPartitionFor(array $document): IPartition
            {
                return $this->partitioning->getPartitions()[0];
            }
        };
    }
}