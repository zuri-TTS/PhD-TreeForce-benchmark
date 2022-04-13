<?php
require_once __DIR__ . '/classes/autoload.php';
require_once __DIR__ . '/benchmark/config/makeConfig.php';
require_once __DIR__ . '/common/functions.php';

\array_shift($argv);

$cmdParser = \Test\CmdArgs::default();

while (! empty($argv)) {
    $current_argv = \parseArgvShift($argv, ';');

    $parsed = $cmdParser->parse($current_argv);
    $dataSets = $parsed['dataSets'];
    $parallelTest = $parsed['args']['parallel'];
    $cmd = $parsed['args']['cmd'];

    if ($parsed['args']['print-java-config'])
        $testClass = '\Test\PrintJavaConfig';
    elseif ($cmd === 'summarize')
        $testClass = '\Test\DoSummarize';
    elseif ($parallelTest)
        $testClass = '\Test\ParallelTest';
    else
        $testClass = '\Test\OneTest';

    $errors = [];

    foreach ($dataSets as $dataSet) {
        $dsPartitions = $dataSet->getPartitions();
        $partitions = [];

        foreach ($dsPartitions as $partition) {
            $logicalPartitioning = $partition->getLogicalPartitioning();

            if ($logicalPartitioning === null)
                $partitions[] = $partition;
            else
                $partitions = \array_merge($partitions, $logicalPartitioning->getPartitionsOf($dataSet));
        }

        if ($parallelTest)
            $partitions = [
                $partitions
            ];

        foreach ($partitions as $partition) {
            $test = new $testClass($dataSet, $partition, $cmdParser);
            $test->execute();
            $test->reportErrors();
            $errors = \array_merge($errors, $test->getErrors());
        }

        if (! empty($errors))
            $test->reportErrors($errors);
    }
}
