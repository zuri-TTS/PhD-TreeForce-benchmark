<?php
namespace Data;

final class NoPartitioning implements IPartitioning
{

    private function __construct()
    {}

    public function getPartitionsOf(\DataSet $ds): array
    {
        $partition = new class($ds) extends PhysicalPartition {

            private string $cname;

            function __construct(\DataSet $ds)
            {
                parent::__construct('');
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
        return '';
    }

    public function getBaseDir(): string
    {
        return '';
    }

    public static function create(): IPartitioning
    {
        return new NoPartitioning();
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