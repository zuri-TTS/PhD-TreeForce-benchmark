<?php

final class Rules
{

    private function __construct()
    {}

    public static function getBasePath()
    {
        return \getBenchmarkBasePath() . '/benchmark/rules_conf/rules';
    }

    public static function getStoragePath()
    {
        return \getBenchmarkBasePath() . '/benchmark/rules';
    }

    public static function getModelsBasePath()
    {
        return self::getStoragePath() . '/models';
    }
}