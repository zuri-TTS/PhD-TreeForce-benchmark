<?php
$basePath = getBenchmarkBasePath();

$measuresNb = 5;
$measuresForget = 1;

$time = \date(DATE_ATOM);
$outDir = $time;

return [
    'jar.path' => "$basePath/software/java/treeforce-demo-0.1-SNAPSHOT.jar",
    'java.opt' => "-Xmx10G",

    'java.properties' => [
        'base.path' => $basePath,
        'output.measures' => "std://out" . ($measuresNb > 1 ? '' : ",\${output.path}/\${query.name}_measures.txt"),
        'query.native' => "",
        'query.batchSize' => 1000,
        'data.batchSize' => 100,
        'leaf.checkTerminal' => 'y',
        'querying.mode' => 'explain',
        'inhibitBatchStreamTime' => 'y',
        'querying.display.answers' => 'n',
        'output.pattern' => '${output.path}/${query.name}_%s.txt',
        'query' => '${queries.dir}/${query.name}',
        'querying.config.print' => 'y',
        'toNative.dots' => 'n',
        'rewritings.deduplicate' => 'n',
        'data' => 'mongodb://localhost/treeforce.${db.collection}',
        'summary.prettyPrint' => 'n',
        'summary.filter.leaf' => 'y'
    ],

    'bench.measures' => [
        'default' => [
            'nb' => $measuresNb,
            // We delete this number of measure from the start and the end of the sorted array of measures
            'forget' => $measuresForget
        ]
    ],
    'bench.cold.function' => function () {
        system('sudo service mongodb stop');
        system('sudo service mongodb start');
        sleep(1);
    },
    'bench.output.base.path' => "$basePath/outputs",
    'bench.output.dir.generator' => function (DataSet $dataSet, array $cmdArg, array $javaProperties): string {
        $group = $dataSet->group();
        $theRules = $dataSet->rules();
        $qualifiers = $dataSet->qualifiersString();

        $cmd = $cmdArg['cmd'];
        $cold = $cmdArg['cold'];
        $executeEach = $cmdArg['each'] ?? false;

        $hasSummary = ! empty($cmdArg['summary']);
        $has2Summary = ! empty($cmdArg['toNative_summary']);
        $hasNative = ! empty($native);

        if ($theRules === 'original') {
            $native = '';
        } else {
            $native = $cmdArg['native'] ?? '';
        }
        $outDir = "[$group][$theRules]$qualifiers";
        $mode = $javaProperties['querying.mode'];

        if ($mode === 'query')
            $mode = '';
        else
            $mode = ":$mode";

        $outDir .= "{{$cmd}$mode}";

        $outDir .= '[%s]';

        if ($hasSummary)
            $outDir .= "[summary-{$cmdArg['summary']}]";
        if ($javaProperties['summary.filter.leaf'] === 'y')
            $outDir .= '[filter-leaf]';
        if ($has2Summary)
            $outDir .= "[toNative-{$cmdArg['toNative_summary']}]";
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
