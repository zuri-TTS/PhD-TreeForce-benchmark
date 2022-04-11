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

    public static function filePattern(): string
    {
        return 'partition.%s.txt';
    }

    public function fileExists(\DataSet $dataSet): bool
    {
        return \is_file($this->filePath($dataSet));
    }

    public function fileName(): string
    {
        return \sprintf(self::filePattern(), $this->getID());
    }

    public function filePath(\DataSet $dataSet): string
    {
        $fname = $this->fileName();
        return "{$dataSet->path()}/$fname";
    }
}