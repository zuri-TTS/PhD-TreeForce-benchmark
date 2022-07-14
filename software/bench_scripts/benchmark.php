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

        $first = \array_key_first($partitions);
        $last = \array_key_last($partitions);

        // Inhibit db clean until the first or last test
        $preCleanDB = $cmdParser['args']['pre-clean-db'] || $cmdParser['args']['clean-db'];
        $postCleanDB = $cmdParser['args']['post-clean-db'] || $cmdParser['args']['clean-db'];

        $cmdParser['args']['clean-db'] = false;
        $cmdParser['args']['pre-clean-db'] = $preCleanDB;

        foreach ($partitions as $k => $partition) {

            if ($k != $first)
                $cmdParser['args']['pre-clean-db'] = false;
            if ($k == $last)
                $cmdParser['args']['post-clean-db'] = $postCleanDB;

            $test = new $testClass($dataSet, $partition, $cmdParser);
            $test->execute();
            $test->reportErrors();
            $errors = \array_merge($errors, $test->getErrors());
        }

        if (! empty($errors))
            $test->reportErrors($errors);
    }
}
