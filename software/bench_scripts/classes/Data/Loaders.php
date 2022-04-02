<?php
namespace Data;

final class Loaders
{

    private function __construct()
    {
        throw new \Error();
    }

    function getPartitioningWithLogical(string $name, array $partitioning, string $defaultJson = 'nopartition'): IPartitioning
    {
        if (empty($name))
            return NoPartitioning::create('', $defaultJson);

        $isLogical = \str_starts_with($name, 'L');

        if ($isLogical) {
            $id = $name;
            $baseDir = \substr($name, 1);
        } else {
            $id = $baseDir = $name;
        }

        if (! \array_key_exists($baseDir, $partitioning))
            throw new \Exception(__CLASS__ . ": invalid partition '$baseDir'; must be one of [" . \implode(',', \array_keys($partitioning)) . "]");

        $partitionPrefix = $partitioning[$baseDir];

        if ($isLogical) {
            return PrefixPartitioning::oneCollection($id, $baseDir, $partitionPrefix);
        } else
            return PrefixPartitioning::create($id, $baseDir, $partitionPrefix);
    }
}