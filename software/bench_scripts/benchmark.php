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
    $cmd = $parsed['args']['cmd'];

    if ($cmd === 'summarize')
        $testClass = '\Test\DoSummarize';
    else
        $testClass = '\Test\OneTest';

    $errors = [];

    foreach ($dataSets as $dataSet) {

        foreach ($dataSet->dataLocation()->getDBCollections() as $coll) {
            $test = new $testClass($dataSet, $coll, $cmdParser);
            $test->execute();
            $test->reportErrors();
            $errors = \array_merge($errors, $test->getErrors());
        }

        if (! empty($errors))
            $test->reportErrors($errors);
    }
}
