<?php
namespace Data;

final class LogicalPrefixPartition extends LogicalPartition
{

    private PhysicalPartition $parent;

    private \DataSet $ds;

    private string $prefix_s;

    private array $prefix;

    private string $cname;

    public function __construct(PhysicalPartition $parent, \DataSet $ds, string $collectionName, string $id, string $prefix)
    {
        parent::__construct($id);
        $this->ds = $ds;
        $this->cname = $collectionName;
        $this->prefix = \explode('.', $prefix);
        $this->prefix_s = $prefix;
        $this->parent = $parent;
    }

    public function getPhysicalParent(): PhysicalPartition
    {
        return $this->parent;
    }

    public function getPrefix(): string
    {
        return $this->prefix_s;
    }

    private function getRangeFilePath(): string
    {
        return "partition.{$this->getID()}.txt";
    }

    public function getLogicalRange(string $partitionID): array
    {
        $fpath = $this->filePath($this->ds, $partitionID);

        if (! \is_file($fpath))
            $ret = [];
        else {
            $contents = \file_get_contents($fpath);
            \preg_match_all('#\d+#', $contents, $matches);
            $ret = $matches[0];
        }
        return $ret;
    }

    public function getCollectionName(): string
    {
        return $this->cname;
    }

    public function contains(array $data): bool
    {
        throw new \Exception("Unsupported operation");
    }
}