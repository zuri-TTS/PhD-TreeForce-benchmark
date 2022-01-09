<?php
include_once __DIR__ . '/xmark_to_json/XMark2Json.php';
include_once __DIR__ . '/common/functions.php';

array_shift($argv);
$dataSet = array_shift($argv);

$basePath = getBenchmarkBasePath();
$dataSetDirPath = "$basePath/benchmark/data/$dataSet";

if (! is_dir($dataSetDirPath)) {
    fputs(STDERR, "Test set '$dataSetDirPath' does not exists");
    exit(1);
}
$configFile = "$basePath/php_configs/xmark2json/$dataSet.php";

if ($configFile === null) {
    fputs(STDERR, "No config file $configFile exists\n");
    exit(1);
} else
    $config = include $configFile;

(new XMark2Json(include "$basePath/php_configs/xmark2json/$dataSet.php"))->convert();
    