<?php
namespace Data;

final class Partitions
{

    private function __construct()
    {
        throw new \Error();
    }

    public static function getCollectionName(\DataSet $ds, \Data\IPartition $p)
    {
        $first = "{$ds->fullGroup()}_{$ds->rules()}" . \DataSets::getQualifiersString($ds->qualifiers());
        $g = $ds->group_partitioning();

        if (\Help\Strings::empty($g) || $g === $ds->group())
            return $first;

        return "$first.{$p->getId()}";
    }

    public static function noPartition(): IPartition
    {
        return new class() implements IPartition {

            function getID(): string
            {
                return '';
            }

            function getPID(): int
            {
                return IPartition::NO_PID;
            }
        };
    }
}