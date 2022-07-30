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

        if (\str_starts_with($name, 'L')) {
            $isLogical = true;
            $subNameOffset = 1;
        } else {
            $isLogical = false;
            $subNameOffset = 0;
        }
        $names = explode('.', $name) + \array_fill(0, 2, '');

        $id = $names[0];
        $baseDir = \substr($id, $subNameOffset);
        $idPartitions = $names[1];

        $isLambda = \str_starts_with($baseDir, 'P');

        if (! \array_key_exists($baseDir, $partitioning))
            throw new \Exception(__CLASS__ . ": invalid partition '$baseDir'; must be one of [" . \implode(',', \array_keys($partitioning)) . "]");

        $partitionPrefix = $partitioning[$baseDir];

        if ($isLambda) {

            if ($isLogical) {
                return PrefixPartitioning::oneLambdaCollection($id, $baseDir, $partitionPrefix, $idPartitions);
            } else
                return PrefixPartitioning::createLambda($id, $baseDir, $partitionPrefix, $idPartitions);
        } else {

            if ($isLogical) {
                return PrefixPartitioning::oneCollection($id, $baseDir, $partitionPrefix, $idPartitions);
            } else
                return PrefixPartitioning::create($id, $baseDir, $partitionPrefix, $idPartitions);
        }
    }
}