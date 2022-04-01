<?php
namespace Data;

final class LogicalPrefixPartitioning implements IPartitioning
{

    private object $noPrefix;

    private array $partitionsPrefix;

    private string $id;

    private string $baseDir;

    private function __construct(string $id, string $baseDir, array $partitionsPrefix)
    {
        $this->id = $id;
        $this->baseDir = $baseDir;
        $this->noPrefix = (object) null;
        $this->partitionsPrefix = $partitionsPrefix;
    }

    public function getPartitionsOf(\DataSet $ds): array
    {
        $ret = [];
        $cname = \MongoImport::getCollectionName($ds);

        $ret[] = new class($ds, $this->id, $cname, $this->partitionsPrefix) extends PhysicalPartition {

            private array $partitionsPrefix;

            private string $cname;

            private \DataSet $ds;

            function __construct(\DataSet $ds, string $id, string $cname, array $partitionsPrefix)
            {
                parent::__construct($id);
                $this->ds = $ds;
                $this->cname = $cname;
                $this->partitionsPrefix = $partitionsPrefix;
            }

            function getLogicalPartitioning(): IPartitioning
            {
                return PrefixPartitioning::create($this->ds, '', $this->partitionsPrefix, true);
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

    public static function create(string $id, string $baseDir, array $partitionsPrefix): IPartitioning
    {
        return new LogicalPrefixPartitioning($id, $baseDir, $partitionsPrefix);
    }
}