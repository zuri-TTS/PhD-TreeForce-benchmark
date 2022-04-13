<?php
namespace Data;

abstract class AbstractPartitioning implements IPartitioning
{

    private string $id;

    private string $baseDir;

    private string $idPartitions;

    abstract function getAllPartitionsOf(\DataSet $ds);

    protected function __construct(string $id, string $baseDir, string $idPartitions = '')
    {
        $this->id = $id;
        $this->baseDir = $baseDir;
        $this->idPartitions = $idPartitions;
    }

    public function getID(): string
    {
        return $this->id;
    }

    public function getBaseDir(): string
    {
        return $this->baseDir;
    }

    public function getPartitionsOf(\DataSet $ds): array
    {
        $partitions = $this->getAllPartitionsOf($ds);
        $partitionsNames = \array_map(fn ($p) => $p->getID(), $partitions);

        $selected = \Help\Thing::allThings(fn () => $partitionsNames, $this->idPartitions);
        return Partitions::selectPartitionsWithID($partitions, $selected);
    }
}