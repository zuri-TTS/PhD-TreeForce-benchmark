<?php
require_once __DIR__ . '/classes/autoload.php';
include_once __DIR__ . '/common/functions.php';

\array_shift($argv);

$cmdArgsDef = [
    'documentstore' => "MongoDB",
    'cmd-display-output' => false,
    'drop-empty' => false,
    'drop' => false,
    'generate' => true,
    // 'summarize' => true,
    'load' => false,
    'pre-clean-ds' => false,
    'pre-clean-db' => false,
    'pre-clean-all' => false,
    'post-clean-ds' => false,
    'simplify_object_useConfig' => true
];

if (empty($argv))
    $argv[] = ";";

$cmdParser = \Test\CmdArgs::cmd($cmdArgsDef);

while (! empty($argv)) {
    $current_argv = \parseArgvShift($argv, ';');
    $cmdParser->parse($current_argv);

    $dataSets = $cmdParser['dataSets'];
    DataSets::checkNotExists($dataSets, false);
    $cmdParsed = $cmdParser['args'];

    // Group by 'group'
    $toProcess = [];
    foreach ($dataSets as $dataSet) {
        $group = $dataSet->group();
        $qualifiers = $dataSet->qualifiersString();
        $toProcess[$group][] = $dataSet;
    }
    $dbImport = \DBImports::get($cmdParser);

    foreach ($toProcess as $dataSets) {
        $qualifiers = $dataSets[0]->qualifiers();

        $group = DataSets::groupOf($dataSets);
        $basepath = DataSets::getGroupPath($group);

        $fclean = function ($datasets, int $mode = \Data\IJsonLoader::CLEAN_ALL) {
            foreach ($datasets as $ds)
                $ds->getJsonLoader()->cleanFiles($mode);
        };

        \wdPush($basepath);

        if ($cmdParsed['pre-clean-db'] || $cmdParsed['pre-clean-all'])
            \array_walk($dataSets, fn ($ds) => $dbImport->dropCollections($ds->getCollections()));

        if ($cmdParsed['drop'])
            \array_walk($dataSets, fn ($ds) => $ds->drop());
        elseif ($cmdParsed['drop-empty'])
            \array_walk($dataSets, fn ($ds) => $ds->dropEmpty());
        else {

            if ($cmdParsed['pre-clean-all'] || $cmdParsed['pre-clean-ds'] === true)
                $fclean($dataSets);
            elseif ($cmdParsed['pre-clean-ds'])
                $fclean($dataSets, (int) $cmdParsed['pre-clean-ds']);

            if ($cmdParsed['generate']) {
                $jsonLoader = DataSets::getJsonLoader(...$dataSets);
                $jsonLoader->generateJson();
            }
            if ($cmdParsed['load'])
                \array_walk($dataSets, fn ($ds) => $dbImport->importDataSet($ds));

            if ($cmdParsed['post-clean-ds'] === true)
                $fclean($dataSets);
            elseif ($cmdParsed['post-clean-ds'])
                $fclean($dataSets, (int) $cmdParsed['post-clean-ds']);
        }
        \wdPop();
    }
}