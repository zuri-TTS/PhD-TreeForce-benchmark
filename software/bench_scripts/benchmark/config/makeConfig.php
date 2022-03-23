<?php

function makeConfig(DataSet $dataSet, string $collection, array &$cmdArg, array $javaProperties) //
{
    $collectionSuffix = \explode('.', $collection, 2)[1] ?? '';
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

    $cprefix = empty($collectionSuffix) ? '' : "$collectionSuffix-";

    $fmakeSummary = function ($type, $suffix) use ($dataSetPath, $cprefix) {
        return empty($type) ? '' : "$dataSetPath/${cprefix}summary-$suffix.txt";
    };

    $javaProperties = array_merge([
        'db.collection' => $collection,
        'queries.dir' => DataSets::getQueriesBasePath($dataSet->group()),
        'rules' => '',
        'summary' => $fmakeSummary($cmdArg['summary'], '${summary.type}'),
        'summary.type' => $cmdArg['summary'],
        'toNative.summary' => $fmakeSummary($cmdArg['toNative_summary'], '${toNative.summary.type}'),
        'toNative.summary.type' => $cmdArg['toNative_summary']
    ], $javaProperties) + $common['java.properties'];

    $outputDirGenerator = $common['bench.output.dir.generator'];
    $outDirPattern = $outputDirGenerator($dataSet, $collection, $cmdArg, $javaProperties);

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

    // <<< >>>

    $ret = array_merge($common, [
        'app.cmd' => $cmd,
        'bench.query.native.pattern' => $hasNative ? "$dataSetPath/queries/%s_each-native-$native.txt" : '',
        'bench.cold' => $cold,
        'dataSet' => $dataSet,
        'summary' => $fmakeSummary($cmdArg['summary'], $cmdArg['summary']),
        'toNative.summary' => $fmakeSummary($cmdArg['toNative_summary'], $cmdArg['toNative_summary']),
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
