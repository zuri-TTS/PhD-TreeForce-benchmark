<?php
namespace Data;

abstract class AbstractPartition implements IPartition
{

    private string $id;

    private int $pid;

    public function __construct(string $id, int $pid)
    {
        $this->id = $id;
        $this->pid = $pid;
    }

    public function getID(): string
    {
        return $this->id;
    }

    public function getPID(): int
    {
        return $this->pid;
    }

    public static final function filePattern(string $id): string
    {
        if ($id === '_id')
            $id = '';
        if (! empty($id))
            $id .= '.';

        return "partition.$id%s.txt";
    }

    public function fileName(string $partitionID): string
    {
        return \sprintf(self::filePattern($partitionID), $this->getID());
    }

    public function filePath(\DataSet $dataSet, string $partitionID): string
    {
        $fname = $this->fileName($partitionID);
        return "{$dataSet->path()}/$fname";
    }
}