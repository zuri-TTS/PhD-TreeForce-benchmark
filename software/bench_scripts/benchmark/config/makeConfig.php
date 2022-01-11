<?php

function selectJavaProperties(array $cmdArg)
{
    $ret = [];

    foreach ($cmdArg as $k => $v) {
        if (! is_string($k))
            continue;
        if ($k[0] !== 'P')
            continue;
        if (is_bool($v))
            $v = $v ? 'y' : 'n';
        $ret[substr($k, 1)] = $v;
    }
    return $ret;
}

function makeConfig(DataSet $dataSet, array $cmdArg) //
{
    $group = $dataSet->getGroup();
    $rules = $dataSet->getRules()[0];

    $cmd = $cmdArg['cmd'];
    $cold = $cmdArg['cold'];
    $executeEach = $cmdArg['each'] ?? false;
    $summaryType = $cmdArg['summary'] ?? '';

    if ($rules === 'original') {
        $hasRules = false;
        $native = '';
    } else {
        $hasRules = true;
        $native = $cmdArg['native'] ?? '';
    }
    $hasSummary = ! empty($summaryType);
    $hasNative = ! empty($native);

    $common = (include __DIR__ . '/common.php');
    $basePath = getBenchmarkBasePath();

    $dbCollection = $dataSet->getGroup() . '_' . $dataSet->getRules()[0];
    $dataBasePath = $dataSet->dataSetPath();

    $outputDirGenerator = $common['bench.output.dir.generator'];
    $outDir = $outputDirGenerator($dataSet, $cmdArg, $common['bench.datetime']);

    $outputPath = "${common['bench.output.base.path']}/$outDir";

    $summaryFileName = "summary-$summaryType.txt";

    $javaProperties = selectJavaProperties($cmdArg);

    $ret = array_merge_recursive($common, [
        'app.cmd' => $cmd,
        'bench.query.native.pattern' => $hasNative ? "$dataBasePath/queries/%s_each-native-$native.txt" : '',
        'bench.cold' => $cold,
        'dataSet' => $dataSet,
        'bench.output.dir' => $outDir
    ]);
    $ret['java.properties'] = array_merge($ret['java.properties'], [
        'db.collection' => $dbCollection,
        'summary.type' => $summaryType,
        'queries.dir' => "$basePath/benchmark/queries",
        'querying.each' => $executeEach ? 'y' : 'n',
        'output.path' => "$outputPath",
        'rules' => '',
        'summary' => $hasSummary ? "$dataBasePath/$summaryFileName" : null
    ] + $javaProperties);

    if ($hasRules)
        $ret['java.properties'] = array_merge($ret['java.properties'], [
            'rules' => $dataSet->rulesPath()
        ]);

    return $ret;
}

function useQuery(array $config, string $query): array
{
    $config["query.native"] = sprintf($config["query.native"], $query);
    return $config;
}
