<?php
namespace Data;

final class NoPartitioning implements IPartitioning
{

    private function __construct()
    {}

    public function getPartitionsOf(\DataSet $ds): array
    {
        $partition = new class($ds) implements IPartition {

            private string $cname;

            function __construct(\DataSet $ds)
            {
                $this->cname = \MongoImport::getCollectionName($ds);
            }

            function getID(): string
            {
                return '';
            }

            function getCollectionName(): string
            {
                return $this->cname;
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

    function getID(): string
    {
        return '';
    }

    public static function create(): IPartitioning
    {
        return new NoPartitioning();
    }
}