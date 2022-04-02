<?php
namespace Data;

final class NoPartitioning implements IPartitioning
{

    private string $id;

    private string $json;

    private ?ILogicalPartitioningFactory $logicalPFactory;

    private function __construct(string $id, string $json, ?ILogicalPartitioningFactory $logicalPFactory = null)
    {
        $this->id = $id;
        $this->json = $json;
        $this->logicalPFactory = $logicalPFactory;
    }

    public function getPartitionsOf(\DataSet $ds): array
    {
        $partition = new class($ds, $this->json, $this->logicalPFactory) extends PhysicalPartition {

            private string $cname;

            private ?IPartitioning $logical;

            function __construct(\DataSet $ds, string $json, ?ILogicalPartitioningFactory $logical)
            {
                parent::__construct('', $json);
                $this->cname = \MongoImport::getCollectionName($ds);
                $this->logicalPFactory = $logical;
            }

            function getCollectionName(): string
            {
                return $this->cname;
            }

            function getLogicalPartitioning(): ?IPartitioning
            {
                if (null === $this->logicalPFactory)
                    return null;

                return $this->logicalPFactory->create($this);
            }

            function contains(array $data): bool
            {
                return true;
            }
        };
        return [
            $partition
        ];
    }

    public function getID(): string
    {
        return $this->id;
    }

    public function getBaseDir(): string
    {
        return '';
    }

    public static function create(string $id, string $json = '', ?ILogicalPartitioningFactory $logicalFactory = null): IPartitioning
    {
        return new NoPartitioning($id, $json, $logicalFactory);
    }
}