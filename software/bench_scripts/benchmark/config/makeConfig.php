<?php

function makeConfig(DataSet $dataSet, $collections, array &$cmdArg, array $javaProperties) //
{
    $group = $dataSet->group();
    $rules = $dataSet->rules();
    $dataSetPath = $dataSet->path();

    $cmd = $cmdArg['cmd'];
    $cold = $cmdArg['cold'];

    if ($rules === 'original') {
        $hasRules = false;
        $native = '';
    } else {
        $hasRules = true;
        $native = $cmdArg['native'] ?? '';
    }
    $hasNative = ! empty($native);

    $common = (include __DIR__ . '/common.php');
    $basePath = getBenchmarkBasePath();

    if ($dataSet->isSimplified()) {

        if (null === $cmdArg['toNative_summary'] || 'key' === $cmdArg['toNative_summary'])
            $cmdArg['toNative_summary'] = 'key-type';
    } elseif (null === $cmdArg['toNative_summary'])
        $cmdArg['toNative_summary'] = $cmdArg['summary'];

    $outputDirGenerator = $common['bench.output.dir.generator'];

    $fsummary = function ($baseDir, $summPrefix, $summType) {
        return "$baseDir/{$summPrefix}summary-$summType.txt";
    };

    if ($cmdArg['parallel']) {

        if (! \is_array($collections))
            throw new \Exception("In parallel mode \$collections must be an array; have $collections");

        $cprefix = "";
        $javaCollection = [];
        $javaSummary = [];
        $javaToNativeSummary = [];

        foreach ($collections as $coll) {
            $collectionSuffix = \explode('.', $coll, 2)[1] ?? '';
            $cprefix = empty($collectionSuffix) ? '' : "$collectionSuffix-";

            $javaCollection[] = $coll;
            $javaSummary[] = $fsummary('${dataset.baseDir}', $cprefix, '${summary.type}');
            $javaToNativeSummary[] = $fsummary('${dataset.baseDir}', $cprefix, '${toNative.summary.type}');

            $benchSummary = $benchToNativeSummary = '';
        }
        $outDirPattern = $outputDirGenerator($dataSet, '', $cmdArg, $javaProperties);
    } else {

        if (! \is_scalar($collections))
            throw new \Exception("In parallel mode \$collections must be scalar; have $collections");

        $collectionSuffix = \explode('.', $collections, 2)[1] ?? '';
        $cprefix = empty($collectionSuffix) ? '' : "$collectionSuffix-";

        $javaCollection = (string) $collections;
        $javaSummary = $fsummary('${dataset.baseDir}', $cprefix, '${summary.type}');
        $javaToNativeSummary = $fsummary('${dataset.baseDir}', $cprefix, '${toNative.summary.type}');

        $benchSummary = $fsummary($dataSetPath, $cprefix, $cmdArg['summary']);
        $benchToNativeSummary = $fsummary($dataSetPath, $cprefix, $cmdArg['toNative_summary']);

        $outDirPattern = $outputDirGenerator($dataSet, $javaCollection, $cmdArg, $javaProperties);
    }

    $javaProperties = array_merge([
        'dataset.baseDir' => $dataSetPath,
        'db.collection' => $javaCollection,
        'queries.dir' => DataSets::getQueriesBasePath($dataSet->group()),
        'rules' => '',
        'summary' => $javaSummary,
        'summary.type' => $cmdArg['summary'],
        'toNative.summary' => $javaToNativeSummary,
        'toNative.summary.type' => $cmdArg['toNative_summary']
    ], $javaProperties) + $common['java.properties'];

    $outDir = sprintf($outDirPattern, $common['bench.datetime']->format('Y-m-d H:i:s v'));

    $pp = $cmdArg['output'] ?? $common['bench.output.base.path'];
    $bpath = \realpath($pp);

    if (false === $bpath)
        throw new \Exception("Error output path '$pp' does not exists");

    // <<< Java properties updates >>>

    $outputPath = $bpath . "/$outDir";
    $javaProperties['output.path'] = $outputPath;

    // Inhibit summary.filter.types for summary without type infos
    if (\in_array($cmdArg['summary'], [
        '',
        'key'
    ]))
        $javaProperties['summary.filter.types'] === 'n';

    // Default value for 'leaf.checkTerminal
    if (null === $javaProperties['leaf.checkTerminal'])
        $javaProperties['leaf.checkTerminal'] = ($javaProperties['summary.filter.types'] === 'n') ? 'y' : 'n';

    if ($cmdArg['parallel'] && $javaProperties['querying.mode'] === 'explain')
        $javaProperties['querying.mode'] = 'explaincolls';

    // <<< >>>

    $gfrom = [
        '[',
        ']',
        '\[',
        '\]'
    ];
    $gto = [
        '\[',
        '\]',
        '[[]',
        '[]]'
    ];
    $testPattern = \sprintf($outDirPattern, "*");
    $testPattern = \str_replace($gfrom, $gto, $testPattern);

    if ($cmdArg['skip-existing']) {
        \wdPush($bpath);
        $test_existing = \glob($testPattern);
        $test_existing = \array_filter($test_existing, fn ($p) => \is_file("$p/@end"));
        \wdPop();
    } else
        $test_existing = null;

    $ret = array_merge($common, [
        'app.cmd' => $cmd,
        'bench.query.native.pattern' => $hasNative ? "$dataSetPath/queries/%s_each-native-$native.txt" : '',
        'bench.cold' => $cold,
        'dataSet' => $dataSet,
        'summary' => $benchSummary,
        'toNative.summary' => $benchToNativeSummary,
        'test.existing' => $test_existing,
        'bench.output.dir' => $outDir,
        'bench.output.path' => $outputPath,
        'bench.output.pattern' => $outDirPattern,
        'bench.plot.types' => $cmdArg['plot'],
        'app.output.display' => $cmdArg['cmd-display-output']
    ]);
    $ret['java.properties'] = $javaProperties;

    if ($hasRules)
        $ret['java.properties'] = array_merge($ret['java.properties'], [
            'rules' => $dataSet->rulesPath()
        ]);
    return $ret;
}
