<?php
require_once __DIR__ . '/classes/autoload.php';
require_once __DIR__ . '/benchmark/config/makeConfig.php';
require_once __DIR__ . '/common/functions.php';

\array_shift($argv);

$cmdArgsDef = [
    'summary' => "key",
    'native' => '',
    'cmd' => 'querying',
    'doonce' => false,
    'cold' => false,
    'output' => null,
    'skip_existing' => true
];

if (empty($argv))
    $argv[] = ";";

while (! empty($argv)) {
    $cmdParsed = $cmdArgsDef;
    $cmdRemains = \updateArray_getRemains(\parseArgvShift($argv, ';'), $cmdParsed);

    $dataSets = \array_filter_shift($cmdRemains, 'is_int', ARRAY_FILTER_USE_KEY);
    $summarize = false;
    $forceNbMeasures = null;

    $javaProperties = \array_filter_shift($cmdRemains, fn ($k) => ($k[0] ?? '') === 'P', ARRAY_FILTER_USE_KEY);

    if (! empty($cmdRemains)) {
        $usage = "\nValid cli arguments are:\n" . \var_export($cmdParsed, true) . //
        "\nor a Java property of the form P#prop=#val\n";
        \fwrite(STDERR, "Unknown cli argument(s):\n" . \var_export($cmdRemains, true) . $usage);
        exit(1);
    }
    $cmdParsed += $javaProperties;

    if (\in_array($cmdParsed['cmd'], [
        'summarize',
        'config'
    ])) {
        $cmdParsed['doonce'] = true;
        $summarize = $cmdParsed['cmd'] === 'summarize';
    } elseif (\in_array($cmdParsed['cmd'], [
        'generate'
    ])) {
        $forceNbMeasures = 1;
    }

    if (\count($dataSets) == 0) {
        $dataSets = [
            null
        ];
    }
    $dataSets = \array_unique(DataSets::all($dataSets));
    DataSets::checkNotExists($dataSets);

    foreach ($dataSets as $dataSet) {
        echo "\n<$dataSet>\n";

        if ($dataSet->isSimplified())
            $cmdParsed['summary'] = 'key-type';

        $config = \makeConfig($dataSet, $cmdParsed);

        $bench = new \Benchmark($config);

        $collection = MongoImport::getCollectionName($dataSet);

        if (! MongoImport::collectionExists($collection))
            throw new \Exception("!!The collection treeforce.$collection must exists in the database!!");

        if ($cmdParsed['skip_existing']) {

            if ($summarize) {
                $path = $config['java.properties']['summary'];

                if (\is_file($path)) {
                    $fname = \basename($path);
                    echo "(Skipped) Summary already exists\n";
                    continue;
                }
            } else if (! empty($existings = $bench->getExistings())) {
                $existings = \implode(",\n", $existings);
                echo "(Skipped) Similar test already exists: $existings\n";
                continue;
            }
        }

        try {
            if ($cmdParsed['doonce'])
                $bench->executeOnce();
            else
                $bench->doTheBenchmark($forceNbMeasures);
        } catch (\Exception $e) {
            \fwrite(STDERR, "<$dataSet>Exception:\n {$e->getMessage()}\n");
        }
    }
}
