<?php
require_once __DIR__ . '/classes/autoload.php';
require_once __DIR__ . '/benchmark/config/makeConfig.php';
require_once __DIR__ . '/common/functions.php';

\array_shift($argv);

$cmdArgsDef = [
    'generate-dataset' => true,
    'cmd-display-output' => false,
    'clean-db' => true,
    'pre-clean-db' => false,
    'post-clean-db' => false,
    'summary' => "key",
    'native' => '',
    'cmd' => 'querying',
    'doonce' => false,
    'cold' => false,
    'output' => null,
    'skip-existing' => true,
    'plot' => 'each,group',
    'forget-results' => false
];

if (empty($argv))
    $argv[] = ";";

while (! empty($argv)) {
    $cmdParsed = $cmdArgsDef;
    $cmdRemains = \updateArray_getRemains(\parseArgvShift($argv, ';'), $cmdParsed, mapArgKey_default(fn ($k) => ($k[0] ?? '') !== 'P'));

    $dataSets = \array_filter_shift($cmdRemains, 'is_int', ARRAY_FILTER_USE_KEY);

    $cmdSummarize = false;
    $forceNbMeasures = null;

    $javaProperties = shiftJavaProperties($cmdRemains);

    if (! empty($cmdRemains)) {
        $usage = "\nValid cli arguments are:\n" . \var_export($cmdParsed, true) . //
        "\nor a Java property of the form P#prop=#val:\n" . \var_export(\getDefaultJavaProperties(), true) . "\n";
        fwrite(STDERR, $usage);
        throw new \Exception("Unknown cli argument(s):\n" . \var_export($cmdRemains, true) );
    }

    if (\in_array($cmdParsed['cmd'], [
        'summarize'
    ])) {
        $cmdParsed['doonce'] = true;
        $cmdSummarize = $cmdParsed['cmd'] === 'summarize';
    } elseif (\in_array($cmdParsed['cmd'], [
        'generate',
        'config'
    ]) || \in_array($javaProperties['querying.mode'], [
        'stats'
    ])) {
        $cmdParsed['cmd-display-output'] = true;
        $cmdParsed['plot'] = false;
        $forceNbMeasures = 1;
    }

    if ($cmdParsed['forget-results'])
        $cmdParsed['plot'] = false;

    if (\count($dataSets) == 0) {
        $dataSets = [
            null
        ];
    }
    $dataSets = \array_unique(DataSets::all($dataSets));

    if ($cmdParsed['pre-clean-db'] || $cmdParsed['clean-db'])
        MongoImport::dropCollections($dataSets);

    $errors = [];
    $cmdParsed_cpy = $cmdParsed;

    foreach ($dataSets as $dataSet) {

        if ($cmdSummarize) {
            echo "\nSummarizing $dataSet\n";
        } else {
            $header = \str_repeat('=', \strlen((string) $dataSet));
            echo "\n$header\nTEST\n$dataSet\n";
        }
        $generateDataSet = $cmdParsed['generate-dataset'];

        if ($dataSet->isSimplified()) {
            $cmdParsed = $cmdParsed_cpy;
            $cmdParsed['summary'] = 'key-type';
        }
        $config = \makeConfig($dataSet, $cmdParsed, $javaProperties);
        $summaryPath = $config['java.properties']['summary'];
        $hasSummary = ! empty($summaryPath);
        $bench = new \Benchmark($config);

        try {

            if ($cmdParsed['skip-existing']) {

                if ($cmdSummarize) {
                    $path = $config['java.properties']['summary'];

                    if (\is_file($path)) {
                        $fname = \basename($path);
                        echo "(Skipped) Summary already exists\n";
                        goto end;
                    }
                } else if (! empty($existings = $bench->getExistings())) {
                    $existings = \implode(",\n", $existings);
                    echo "(Skipped) Similar test already exists: $existings\n";
                    goto end;
                }
            }
            // ================================================================
            $collection = MongoImport::getCollectionName($dataSet);
            $collExists = MongoImport::collectionExists($collection);

            if ($generateDataSet && (! $dataSet->exists() || ! $collExists || //
            ($hasSummary && ! $cmdSummarize && ! \is_file($summaryPath)) //
            )) //
            {
                $vars = [
                    '',
                    $dataSet->id(),
                    '+load'
                ];
                include_script(__DIR__ . '/xmark_to_json.php', $vars);
                $vars = [
                    '',
                    $dataSet->id(),
                    'cmd=summarize',
                    '+skip-existing',
                    '-generate-dataset',
                    '-clean-db',
                    "output:",
                    \sys_get_temp_dir(),
                    '+plot'
                ];
                include_script(__DIR__ . '/benchmark.php', $vars);
                \clearstatcache();
            }
            // ================================================================

            if (! $generateDataSet) {

                if (! $collExists)
                    throw new \Exception("The collection treeforce.$collection must exists in the database");

                if (! $cmdSummarize && $hasSummary && ! \is_file($summaryPath))
                    throw new \Exception("Summary '$summaryPath' does not exists");
            }

            if ($cmdParsed['doonce'])
                $bench->executeOnce();
            else
                $bench->doTheBenchmark($forceNbMeasures);

            end:

            if ($cmdParsed['post-clean-db'] || $cmdParsed['clean-db']) {
                MongoImport::dropCollection($dataSet);
                $vars = [
                    '',
                    $dataSet->id(),
                    '+drop',
                    '+cmd-display-output'
                ];
                include_script(__DIR__ . '/xmark_to_json.php', $vars);
            }

            if ($cmdParsed['forget-results'])
                \rrmdir($config['java.properties']['output.path']);
        } catch (\Exception $e) {
            $errors[] = [
                'dataset' => $dataSet,
                'exception' => $e
            ];
            \fwrite(STDERR, "<$dataSet>Exception:\n {$e->getMessage()}\n");
        }
    }

    if (! empty($errors)) {
        \ob_start();
        echo "\n== Error reporting ==\n\n";

        foreach ($errors as $err) {
            echo "===\n{$err['dataset']}\n{$err['exception']->getMessage()}\n{$err['exception']->getTraceAsString()}\n\n";
        }
        \fwrite(STDERR, \ob_get_clean());
    }
}
