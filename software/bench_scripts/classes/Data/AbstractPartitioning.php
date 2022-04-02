<?php
namespace Data;

abstract class AbstractPartitioning implements IPartitioning
{

    private string $id;

    private string $baseDir;

    protected function __construct(string $id, string $baseDir)
    {
        $this->id = $id;
        $this->baseDir = $baseDir;
    }

    public function getID(): string
    {
        return $this->id;
    }

    public function getBaseDir(): string
    {
        return $this->baseDir;
    }
}