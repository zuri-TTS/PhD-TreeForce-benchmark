<?php

final class DBImports
{

    public static function get(\Test\CmdArgs $cmdParser): \DBImport\IDBImport
    {
        $class = "\\DBImport\\{$cmdParser['args']['documentstore']}Import";
        return new $class();
    }

    public static function getCollectionName(DataSet $dataSet)
    {
        if (! $dataSet->hasQueryingVocabulary())
            return $dataSet->group() . DataSets::getQualifiersString($dataSet->qualifiers());

        return \str_replace('/', '_', $dataSet->id());
    }
}