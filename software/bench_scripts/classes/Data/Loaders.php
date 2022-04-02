<?php
namespace Data;

final class Loaders
{

    private function __construct()
    {
        throw new \Error();
    }

    function getPartitioningWithLogical(string $name, array $partitioning, string $defaultId = 'nopartition'): IPartitioning
    {
        if (empty($name))
            return \Data\NoPartitioning::create($defaultId);

        if (\str_starts_with($name, 'L')) {
            $class = '\Data\LogicalPrefixPartitioning';
            $id = $name;
            $baseDir = \substr($name, 1);
        } else {
            $class = '\Data\PrefixPartitioning';
            $id = $baseDir = $name;
        }

        if (! \array_key_exists($baseDir, $partitioning))
            throw new \Exception(__CLASS__ . ": invalid partition '$baseDir'; must be one of [" . \implode(',', \array_keys($partitioning)) . "]");

        return $class::create($id, $baseDir, $partitioning[$baseDir]);
    }
}