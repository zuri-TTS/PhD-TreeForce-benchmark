<?php
require_once __DIR__ . '/benchmark/Benchmark.php';
require_once __DIR__ . '/benchmark/php_config/makeConfig.php';
require_once __DIR__ . '/common/functions.php';

\array_shift($argv);

if (empty($argv)) {
    \fputs(STDERR, "No argument provided");
    exit(1);
}

$confDef = [
    'summary' => "key",
    'rules' => true,
    'each' => false,
    'native' => '',
    'cmd' => 'querying',
    'data' => 'noised',
    'doonce' => false,
    'cold' => false
];

$dataSet = \array_shift($argv);
$dataSetDirPath = getBenchmarkBasePath() . "/benchmark/data/$dataSet";

if (! is_dir($dataSetDirPath)) {
    fputs(STDERR, "Test set '$dataSetDirPath' does not exists");
    exit(1);
}
$cmdArg = \parseArgv($argv) + $confDef;
$cmdArg['dataSet'] = $dataSet;

$config = makeConfig($cmdArg);
$bench = new \Benchmark($config);

if ($cmdArg['doonce'])
    $bench->executeOnce();
else
    $bench->doTheBenchmark();
        