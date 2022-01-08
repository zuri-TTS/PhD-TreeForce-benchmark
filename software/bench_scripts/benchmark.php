<?php
require_once __DIR__ . '/benchmark/Benchmark.php';
require_once __DIR__ . '/benchmark/php_config/makeConfig.php';

\array_shift($argv);

if (empty($argv)) {
    \fputs(STDERR, "No config file for input");
    exit(1);
}

function parseArgv($argv): array
{
    $conf = [
        'summary' => "key",
        'rules' => true,
        'each' => false,
        'native' => '',
        'cmd' => 'querying',
        'data' => 'noised',
        'doonce' => false,
        'cold' => false,
    ];

    foreach ($argv as $arg) {

        if ($arg[0] === '+' || $arg[0] === '-') {
            $name = \substr($arg, 1);
            $conf[$name] = $arg[0] === '+';
        } else {
            [
                $name,
                $val
            ] = explode('=', $arg, 2);
            $conf[$name] = $val;
        }
    }
    return $conf;
}

$dataSet = \array_shift($argv);
$dataSetDirPath = getBenchmarkBasePath() . "/benchmark/data/$dataSet";

if (! is_dir($dataSetDirPath)) {
    fputs(STDERR, "Test set '$dataSetDirPath' does not exists");
    exit(1);
}
$cmdArg = \parseArgv($argv);
$cmdArg['dataSet'] = $dataSet;

$config = makeConfig($cmdArg);
$bench = new \Benchmark($config);

if ($cmdArg['doonce'])
    $bench->executeOnce();
else
    $bench->doTheBenchmark();
        