<?php
namespace Data;

final class Partitions
{

    private function __construct()
    {
        throw new \Error();
    }

    public static function getCollectionsOf(array $partitions)
    {
        $ret = [];

        foreach ($partitions as $p) {
            $ret[] = $p->getCollectionName();
        }
        return $ret;
    }

    public static function selectPartitionsWithID(array $partitions, array $IDs): array
    {
        $ret = [];

        foreach ($partitions as $p)
            if (\in_array($p->getID(), $IDs))
                $ret[] = $p;

        return $ret;
    }

    public static function getPartitionWithCollectionName(array $partitions, string $collectionName): IPartition
    {
        foreach ($partitions as $p)
            if ($p->getCollectionName() === $collectionName)
                return $p;

        // Error
        return null;
    }

    public static function getPartitionForData(array $partitions, array $data): IPartition
    {
        foreach ($partitions as $p)
            if ($p->contains($data))
                return $p;

        // Error
        return null;
    }

    public static function noPartition(): IPartition
    {
        return new class() implements IPartition {

            function getID(): string
            {
                return '';
            }

            function getCollectionName(): string
            {
                return '';
            }

            function contains(array $data): bool
            {
                return false;
            }

            function isLogical(): bool
            {
                return true;
            }

            function getLogicalRange(): ?array
            {
                return null;
            }
        };
    }
}