<?php
namespace Data;

final class PrefixPartitioning implements IPartitioning
{

    private array $partitionsPrefix;

    private string $id;

    private string $baseDir;

    private bool $logical;

    private function __construct(string $id, string $baseDir, array $partitionsPrefix, bool $logical)
    {
        $this->id = $id;
        $this->baseDir = $baseDir;
        $this->partitionsPrefix = $partitionsPrefix;
        $this->logical = $logical;
    }

    public function getPartitionsOf(\DataSet $ds): array
    {
        $ret = [];
        $cname = \MongoImport::getCollectionName($ds);

        foreach ($this->partitionsPrefix as $name => $prefix) {
            $ccname = $this->logical ? $cname : "$cname.$prefix";
            $ret[] = self::createPartition($ds, $ccname, $name, $prefix, $this->logical);
        }
        return $ret;
    }

    public function getID(): string
    {
        return $this->id;
    }

    public function getBaseDir(): string
    {
        return $this->baseDir;
    }

    public static function create(string $id, string $baseDir, array $partitionsPrefix, bool $logical = false): IPartitioning
    {
        return new PrefixPartitioning($id, $baseDir, $partitionsPrefix, $logical);
    }

    private static function createPartition(\DataSet $ds, string $collectionName, string $id, string $prefix, bool $logical = false)
    {
        $classPartition = '\\Data\\' . ($logical ? 'LogicalPrefixPartition' : 'PrefixPartition');
        return new $classPartition($ds, $collectionName, $id, $prefix, $logical);
    }
}