<?php
namespace Data;

abstract class AbstractPartition implements IPartition
{

    private string $id;

    protected function __construct(string $id)
    {
        $this->id = $id;
    }

    public function getID(): string
    {
        return $this->id;
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