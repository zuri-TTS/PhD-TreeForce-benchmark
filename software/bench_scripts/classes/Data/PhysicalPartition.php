<?php
namespace Data;

abstract class PhysicalPartition implements IPartition
{

    private string $id;

    private string $json;

    private ?IPartitioning $logical;

    protected function __construct(string $id, string $json = '', ?Ipartitioning $logical = null)
    {
        $this->id = $id;
        $this->json = empty($json) ? "$id.json" : "$json.json";
        $this->logical = $logical;
    }

    public function getID(): string
    {
        return $this->id;
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