<?php

final class Queries
{

    private function __construct()
    {}

    public static function getAll(bool $getPath = true): array
    {
        $path = self::getBasePath();
        $queries = \scandirNoPoints($path);
        $ret = \array_filter($queries, fn ($f) => \is_file("$path/$f"));
        \sort($ret);

        if ($getPath)
            return \array_map(fn ($f) => "$path/$f", $ret);

        return $ret;
    }

    public static function getBasePath()
    {
        return \getBenchmarkBasePath() . '/benchmark/queries_conf/queries';
    }

    public static function getStoragePath()
    {
        return \getBenchmarkBasePath() . '/benchmark/queries';
    }

    public static function getModelsBasePath()
    {
        return \getBenchmarkBasePath() . '/benchmark/queries/models';
    }
}