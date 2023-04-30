<?php
date_default_timezone_set('Europe/Paris');
$basePath = getBenchmarkBasePath();

$time = \date(DATE_ATOM);
$outDir = $time;

return [
    'datetime.format' => 'Y-m-d H:i:s v',
    'jar.path' => "$basePath/software/java/hi-rule-0.1-SNAPSHOT.jar",
    'java.opt' => "-Xmx10G",

    'java.properties' => [
        'base.path' => $basePath,
        'output.measures' => '${output.path}/${query.name}_measures-${bench.measures.i}.txt',
        'query.batches.nbThreads' => 1,
        'query.native' => "",
        'query.batchSize' => 1000,
        'data.batchSize' => 100,
        'leaf.checkTerminal' => null,
        'querying.mode' => 'explain',
        'querying.filter' => '',
        'inhibitBatchStreamTime' => 'y',
        'querying.display.answers' => 'n',
        'output.pattern' => '${output.path}/${query.name}_%s.txt',
        'query' => '${queries.dir}/${query.name}',
        'querying.config.print' => 'y',
        'querying.timeout' => 0,
        'toNative.dots' => 'n',
        'rewritings.deduplicate' => 'n',
        'rewritings.output.disjunctions' => 'y',
        'summary.prettyPrint' => 'n',
        'summary.filter.types' => 'y',
        'summary.filter.stringValuePrefix' => 0,
        'disjunction.sequential' => true
    ],
    'bench.measures' => [
        'default' => [
            'nb' => null,
            // We delete this number of measure from the start and the end of the sorted array of measures
            'forget' => null
        ]
    ],
    'bench.cold.function' => function () {
        system('sudo service mongodb stop');
        system('sudo service mongodb start');
        sleep(1);
    },
    'bench.output.base.path' => function (DataSet $dataSet, \Test\CmdArgs $cmdParser) use ($basePath) {

        $args = $cmdParser['args'];
        $partitioning = $dataSet->getPartitioning();

        $hasPartitioning = \count($partitioning->getPartitions()) > 1;
        $j = $cmdParser['javaProperties'];

        $pid = $hasPartitioning ? "-{$dataSet->pidKey()}" : '';
        $ds = $dataSet->group();

        if ($hasPartitioning)
            $ds .= ".{$partitioning->getID()}";

        $parallel = $args['parallel'] ? '-parallel' : '';
        $prefixSize = $j['summary.filter.stringValuePrefix'] > 0 ? "-prefix_{$j['summary.filter.stringValuePrefix']}" : '';
        $filter = ! empty($j['querying.filter']) ? "-{$j['querying.filter']}" : '';
        $batchesNbThread = $j['query.batches.nbThreads'] > 1 ? "-t{$j['query.batches.nbThreads']}-qb{$j['query.batchSize']}" : '';

        return "$basePath/outputs/{$args['bench-measures-nb']}/$ds$pid$parallel$prefixSize$filter$batchesNbThread";
    },
    'bench.output.dir.generator' => function (DataSet $dataSet, \Data\IPartition $partition, array $cmdArg, array $javaProperties): string {
        $group = $dataSet->group();
        $theRules = $dataSet->rules();
        $qualifiers = $dataSet->qualifiersString('[]');
        $qualifiers = \substr($qualifiers, 1, - 1);

        $cmd = $cmdArg['cmd'];
        $cold = $cmdArg['cold'];
        $executeEach = $cmdArg['each'] ?? false;

        $hasSummary = ! empty($cmdArg['summary']);
        $has2Summary = ! empty($cmdArg['toNative_summary']);
        $hasNative = ! empty($native);
        $hasPartitioning = ! ($dataSet->getPartitioning() instanceof \Data\NoPartitioning);

        if ($theRules === 'original') {
            $native = '';
        } else {
            $native = $cmdArg['native'] ?? '';
        }

        if ($dataSet->group_partitioning() === '')
            $partid = '';
        else
            $partid = $partition->getID();

        $elements = [
            'group' => $group,
            'partitioning' => $dataSet->group_partitioning(),
            'partition' => $partid,
            'rules' => $theRules,
            'qualifiers' => $qualifiers,
            'summary' => $cmdArg['summary'],
            'toNative' => $cmdArg['toNative_summary'],
            'parallel' => $cmdArg['parallel'],
            'partition_id' => $dataSet->pidKey(),
            'filter_types' => $javaProperties['summary.filter.types'] !== 'n',
            'filter_prefix' => $javaProperties['summary.filter.stringValuePrefix']
        ];
        $outDir = \Help\Plotter::encodeDirNameElements($elements);
        $outDir = \sprintf($outDir, '[%s]');

        if ($hasNative)
            $outDir .= "[native-$native]";
        if ($javaProperties['querying.mode'] === 'each')
            $outDir .= '[each]';
        elseif ($javaProperties['querying.mode'] === 'stats')
            $outDir .= '[stats]';
        if ($javaProperties['toNative.dots'] === 'y')
            $outDir .= '[dots]';
        if ($cold)
            $outDir .= '[COLD]';

        return $outDir;
    },
    'bench.datetime' => new DateTimeImmutable()
];
