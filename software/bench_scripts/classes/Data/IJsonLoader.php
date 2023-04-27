<?php
namespace Data;

interface IJsonLoader
{

    public const CLEAN_BASE_FILES = 1;

    public const CLEAN_JSON_FILES = 2;

    public const CLEAN_ALL = self::CLEAN_BASE_FILES | self::CLEAN_JSON_FILES;

    // ========================================================================
    function __construct(array $datasets, array $config);

    function generateJson(): void;

    function getPartitioning(string $name = ''): IPartitioning;

    function cleanFiles(int $level = self::CLEAN_ALL);
}