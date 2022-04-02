<?php
namespace Data;

final class NoPartitioning implements IPartitioning
{

    private string $id;

    function __construct(string $id)
    {
        $this->id = $id;
    }

    public function getPartitionsOf(\DataSet $ds): array
    {
        $partition = new class($ds, $this->id) extends PhysicalPartition {

            private string $cname;

            function __construct(\DataSet $ds, string $id)
            {
                parent::__construct($id);
                $this->cname = \MongoImport::getCollectionName($ds);
            }

            function getCollectionName(): string
            {
                return $this->cname;
            }

            function getLogicalPartitioning(): IPartitioning
            {
                return $this;
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

    public static function create(string $id = ''): IPartitioning
    {
        return new NoPartitioning($id);
    }

    public static function noPartition(): IPartition
    {
        return new class() implements IPartition {

            function getID(): string
            {
                return '';
            }

            function getCollectionName(): string
            {
                return '';
            }

            function contains(array $data): bool
            {
                return false;
            }

            function isLogical(): bool
            {
                return true;
            }

            function getLogicalRange(): ?array
            {
                return null;
            }
        };
    }
}