
<?php
require_once __DIR__ . '/classes/autoload.php';
include_once __DIR__ . '/common/functions.php';

\array_shift($argv);

$cmdArgsDef = [
    'cmd-display-output' => false,
    'drop-empty' => false,
    'drop' => false,
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

while (! empty($argv)) {
    $cmdParsed = $cmdArgsDef;
    $cmdRemains = \updateArray_getRemains(\parseArgvShift($argv, ';'), $cmdParsed);

    $dataSets = \array_filter_shift($cmdRemains, 'is_int', ARRAY_FILTER_USE_KEY);

    $javaProperties = \array_filter_shift($cmdRemains, fn ($k) => ($k[0] ?? '') === 'P', ARRAY_FILTER_USE_KEY);

    if (! empty($cmdRemains)) {
        $usage = "\nValid cli arguments are:\n" . \var_export($cmdParsed, true) . //
        "\nor a Java property of the form P#prop=#val\n";
        throw new \Exception("Unknown cli argument(s):\n" . \var_export($cmdRemains, true) . $usage);
    }
    $cmdParsed += $javaProperties;

    if (\count($dataSets) == 0) {
        $dataSets = [
            null
        ];
    }
    $dataSets = \array_unique(DataSets::all($dataSets));
    DataSets::checkNotExists($dataSets, false);

    // Group by 'group'
    $toProcess = [];
    foreach ($dataSets as $dataSet) {
        $group = $dataSet->group();
        $qualifiers = $dataSet->qualifiersString();
        $toProcess["$group"][] = $dataSet;
    }

    foreach ($toProcess as $dataSets) {
        $qualifiers = $dataSets[0]->qualifiers();
        $converter = (new \XMLLoader($dataSets))-> //
        summarize($cmdParsed['summarize']);

        if ($cmdParsed['pre-clean-db'] || $cmdParsed['pre-clean-all'])
            MongoImport::dropCollections($dataSets);

        if ($cmdParsed['pre-clean'] || $cmdParsed['pre-clean-all'])
            $converter->clean();

        if ($cmdParsed['drop'])
            $converter->drop();
        elseif ($cmdParsed['drop-empty'])
            $converter->dropEmpty();
        elseif ($cmdParsed['generate'])
            $converter->convert();

        if ($cmdParsed['load'])
            \array_walk($dataSets, 'MongoImport::importDataSet');

        if ($cmdParsed['post-clean'] || $cmdParsed['post-clean-all'])
            $converter->clean();

        if ($cmdParsed['post-clean-xml'] || $cmdParsed['post-clean-all'])
            $converter->deleteXMLFile();
    }
}