<?php
namespace Data;

final class SimplePartitioning
{

    // private function __construct(string $id, string $json, ?ILogicalPartitioningFactory $logicalPFactory = null)
    // {
    // $this->id = $id;
    // $this->json = $json;
    // $this->logicalPFactory = $logicalPFactory;
    // }

    // function getPartitionFor(array $document): IPartition
    // {}

    // private function getPartitionsOf(\DataSet $ds): array
    // {
    // $partition = new class($ds, $this->json, $this->logicalPFactory) extends PhysicalPartition {

    // private string $cname;

    // private ?IPartitioning $logical;

    // function __construct(\DataSet $ds, string $json, ?ILogicalPartitioningFactory $logical)
    // {
    // parent::__construct('', $json);
    // $this->cname = \DBImports::getCollectionName($ds);
    // $this->logicalPFactory = $logical;
    // }

    // function getCollectionName(): string
    // {
    // return $this->cname;
    // }

    // function getLogicalPartitioning(): ?IPartitioning
    // {
    // if (null === $this->logicalPFactory)
    // return null;

    // return $this->logicalPFactory->create($this);
    // }

    // function contains(array $data): bool
    // {
    // return true;
    // }
    // };
    // return [
    // $partition
    // ];
    // }

    // public function getID(): string
    // {
    // return $this->id;
    // }

    // public function getBaseDir(): string
    // {
    // return '';
    // }

    // public static function create(string $id, ?string $json = null, ?ILogicalPartitioningFactory $logicalFactory = null): IPartitioning
    // {
    // return new NoPartitioning($id, $json ?? "$id.json", $logicalFactory);
    // }
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