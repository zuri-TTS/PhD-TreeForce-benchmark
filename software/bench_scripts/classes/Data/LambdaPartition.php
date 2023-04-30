<?php
namespace Data;

final class LambdaPartition implements IPartition
{

    private array $depthKeys;

    private string $id;

    private int $pid;

    public function __construct(array $depthKeys, string $id, int $pid)
    {
        $this->depthKeys = $depthKeys;
        $this->id = $id;
        $this->pid = $pid;
    }

    public static function __set_state($properties)
    {
        return new LambdaPartition($properties['depthKeys'], $properties['id'], $properties['pid']);
    }

    public function getDepthKeys(): array
    {
        return $this->depthKeys;
    }

    public function getPID(): int
    {
        return $this->pid;
    }

    public function getID(): string
    {
        return $this->id;
    }

    public function getDepth(): int
    {
        return \count($this->partitions);
    }

    public function contains(array $data): bool
    {
        return \Help\Arrays::getDepthKeys($data, $this->getDepth()) === $this->partitions;
    }

    public function isLogical(): bool
    {
        return false;
    }
}