<?php
namespace Data;

final class PrefixPartitioning extends AbstractPartitioning
{

    private array $partitionsPrefix;

    private function __construct(string $id, string $baseDir, array $partitionsPrefix)
    {
        parent::__construct($id, $baseDir);
        $this->partitionsPrefix = $partitionsPrefix;
    }

    public function getPartitionsOf(\DataSet $ds): array
    {
        $ret = [];
        $cname = \MongoImport::getCollectionName($ds);

        foreach ($this->partitionsPrefix as $name => $prefix) {
            $ccname = "$cname.$name";
            $ret[] = new PrefixPartition($ds, $ccname, $name, $prefix);
        }
        return $ret;
    }

    // ========================================================================
    public static function create(string $id, string $baseDir, array $partitionsPrefix): IPartitioning
    {
        return new PrefixPartitioning($id, $baseDir, $partitionsPrefix);
    }
}