<?php
namespace Data;

abstract class PhysicalPartition extends AbstractPartition
{

    private string $json;

    private ?IPartitioning $logical;

    protected function __construct(string $id, string $json = '', ?IPartitioning $logical = null)
    {
        parent::__construct($id);
        $this->json = empty($json) ? "$id.json" : "$json.json";
        $this->logical = $logical;
    }

    public function setLogicalPartitioning(?IPartitioning $logical)
    {
        $this->logical = $logical;
    }

    public function getJsonFile(): string
    {
        return $this->json;
    }

    public function getLogicalPartitioning(): ?IPartitioning
    {
        return $this->logical;
    }

    public function isLogical(): bool
    {
        return false;
    }
}