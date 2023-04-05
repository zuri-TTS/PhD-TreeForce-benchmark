
<?php
require_once __DIR__ . '/classes/autoload.php';
include_once __DIR__ . '/common/functions.php';

\array_shift($argv);

$cmdArgsDef = [
    'documentstore' => "MongoDB",
    'cmd-display-output' => false,
    'drop-empty' => false,
    'drop' => false,
    'clean' => false,
    'generate' => true,
    'summarize' => true,
    'load' => false,
    'pre-clean' => false,
    'pre-clean-db' => false,
    'pre-clean-all' => false,
    'post-clean' => false,
    'post-clean-xml' => false,
    'post-clean-all' => false,
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
        $toProcess["$group"][] = $dataSet;
    }
    $dbImport = \DBImports::get($cmdParser);

    foreach ($toProcess as $dataSets) {
        $qualifiers = $dataSets[0]->qualifiers();
        $converter = (new \XMLLoader($dbImport, $dataSets))-> //
        summarize($cmdParsed['summarize']);

        if ($cmdParsed['pre-clean-db'] || $cmdParsed['pre-clean-all'])
            $dbImport->dropCollections($dataSets);

        if ($cmdParsed['pre-clean'] || $cmdParsed['pre-clean-all'])
            $converter->clean();

        if (false !== $cmdParsed['clean']) {

            if ($cmdParsed['clean'] === true)
                $converter->clean();
            else
                $converter->clean($cmdParsed['clean']);
        } elseif ($cmdParsed['drop'])
            $converter->drop();
        elseif ($cmdParsed['drop-empty'])
            $converter->dropEmpty();
        elseif ($cmdParsed['generate'])
            $converter->convert();

        if ($cmdParsed['load'])
            $converter->load();

        if ($cmdParsed['post-clean'] || $cmdParsed['post-clean-all'])
            $converter->clean();

        if ($cmdParsed['post-clean-xml'] || $cmdParsed['post-clean-all'])
            $converter->deleteXMLFile();
    }
}