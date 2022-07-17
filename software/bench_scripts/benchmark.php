<?php
require_once __DIR__ . '/classes/autoload.php';
require_once __DIR__ . '/benchmark/config/makeConfig.php';
require_once __DIR__ . '/common/functions.php';

\array_shift($argv);

$cmdParser = \Test\CmdArgs::default();

while (! empty($argv)) {
    $current_argv = \parseArgvShift($argv, ';');
    $cmdParser->parse($current_argv);

    $dataSets = $cmdParser['dataSets'];
    $cmd = $cmdParser['args']['cmd'];

    if ($cmdParser['args']['print-java-config'])
        $testClass = '\Test\PrintJavaConfig';
    elseif ($cmd === 'summarize')
        $testClass = '\Test\DoSummarize';
    else
        $testClass = '\Test\OneTest';

    $preCleanDB = $cmdParser['args']['pre-clean-db'] || $cmdParser['args']['clean-db'];
    $postCleanDB = $cmdParser['args']['post-clean-db'] || $cmdParser['args']['clean-db'];

    // Inhibit db clean until the first or last test
    $cmdParser['args']['clean-db'] = false;
    $cmdParser['args']['pre-clean-db'] = $preCleanDB;
    $cmdExpansions = $cmdParser->expand();

    $errors = [];

    foreach ($dataSets as $dataSet) {
        $dsPartitions = $dataSet->getPartitions();
        $partitions = [];

        foreach ($dsPartitions as $subPartitions) {
            $logicalPartitioning = $subPartitions->getLogicalPartitioning();

            // $subPartitions is one PhysicalPartition
            if ($logicalPartitioning === null)
                $partitions[] = $subPartitions;
            else
                $partitions = \array_merge($partitions, $logicalPartitioning->getPartitionsOf($dataSet));
        }

        $cLast = \array_key_last($cmdExpansions);

        foreach ($cmdExpansions as $kk => $cmdFinalParser) {
            $parallelTest = $cmdFinalParser['args']['parallel'];

            if ($parallelTest)
                $pp = [
                    $partitions
                ];
            else
                $pp = \array_map(fn ($p) => [
                    $p
                ], $partitions);

            $pLast = \array_key_last($pp);

            foreach ($pp as $k => $subPartitions) {

                if ($k != 0 || $kk != 0)
                    $cmdFinalParser['args']['pre-clean-db'] = false;
                if ($k == $pLast && $kk == $cLast)
                    $cmdFinalParser['args']['post-clean-db'] = $postCleanDB;

                $test = new $testClass($dataSet, $cmdFinalParser, ...$subPartitions);
                $test->execute();
                $test->reportErrors();
                $errors = \array_merge($errors, $test->getErrors());
            }
        }

        if (! empty($errors))
            $test->reportErrors($errors);
    }
}
