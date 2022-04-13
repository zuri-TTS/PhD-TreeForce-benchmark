<?php
namespace Data;

final class LogicalPrefixPartitioning extends AbstractPartitioning
{

    private PhysicalPartition $parent;

    private array $partitionsPrefix;

    public function __construct(PhysicalPartition $parent, string $id, string $baseDir, array $partitionsPrefix)
    {
        parent::__construct($id, $baseDir);
        $this->parent = $parent;
        $this->partitionsPrefix = $partitionsPrefix;
    }

    public function getAllPartitionsOf(\DataSet $ds): array
    {
        $ret = [];
        $cname = \MongoImport::getCollectionName($ds);

        foreach ($this->partitionsPrefix as $name => $prefix) {
            $ret[] = new LogicalPrefixPartition($this->parent, $ds, $cname, $name, $prefix);
        }
        return $ret;
    }

    // ========================================================================
    public static function createFactory(string $id, string $baseDir, array $partitionsPrefix): ILogicalPartitioningFactory
    {
        return new class($id, $baseDir, $partitionsPrefix) implements ILogicalPartitioningFactory {

            function __construct($id, $baseDir, $partitionsPrefix)
            {
                $this->id = $id;
                $this->baseDir = $baseDir;
                $this->partitionsPrefix = $partitionsPrefix;
            }

            function create(PhysicalPartition $parent): IPartitioning
            {
                return new LogicalPrefixPartitioning($parent, $this->id, $this->baseDir, $this->partitionsPrefix);
            }
        };
    }
}