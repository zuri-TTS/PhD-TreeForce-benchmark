<?php
namespace Data;

final class PrefixPartitioning extends AbstractPartitioning
{

    private array $partitionsPrefix;

    private bool $oneCollection;

    private function __construct(bool $oneCollection, string $id, string $baseDir, array $partitionsPrefix, string $idPartitions)
    {
        parent::__construct($id, $baseDir, $idPartitions);
        $this->partitionsPrefix = $partitionsPrefix;
        $this->oneCollection = $oneCollection;
    }

    public function getAllPartitionsOf(\DataSet $ds): array
    {
        $ret = [];

        $cname = \MongoImport::getCollectionName($ds);

        foreach ($this->partitionsPrefix as $name => $prefix) {

            if ($this->oneCollection) {
                $partition = new PrefixPartition($ds, $cname, $name, $prefix);
                $lpartitioning = new LogicalPrefixPartitioning($partition, $cname, $name, [
                    $name => $prefix
                ]);
                $partition->setLogicalPartitioning($lpartitioning);
                $ret[] = $partition;
            } else {
                $ccname = "$cname.$name";
                $ret[] = new PrefixPartition($ds, $ccname, $name, $prefix);
            }
        }
        return $ret;
    }

    // ========================================================================
    public static function create(string $id, string $baseDir, array $partitionsPrefix, string $idPartitions = ''): IPartitioning
    {
        return new PrefixPartitioning(false, $id, $baseDir, $partitionsPrefix, $idPartitions);
    }

    public static function oneCollection(string $id, string $baseDir, array $partitionsPrefix, string $idPartitions = ''): IPartitioning
    {
        return new PrefixPartitioning(true, $id, $baseDir, $partitionsPrefix, $idPartitions);
    }
}