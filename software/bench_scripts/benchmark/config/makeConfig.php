<?php

function selectJavaProperties(array $cmdArg)
{
    $ret = [];

    foreach ($cmdArg as $k => $v) {
        if(!is_string($k))
            continue;
        if ($k[0] !== 'P')
            continue;

        $ret[substr($k, 1)] = $v;
    }
    return $ret;
}

function makeConfig(DataSet $dataSet, array $cmdArg) //
{
    $group = $dataSet->getGroup();
    $rules = $dataSet->getRules()[0];

    if ($rules === 'original') {
        $hasRules = false;
        $summaryType = "";
        $native = '';
    } else {
        $hasRules = true;
        $summaryType = ! empty($hasRules) ? $cmdArg['summary'] : "";
        $native = $cmdArg['native'] ?? '';
    }

    $cmd = $cmdArg['cmd'];
    $cold = $cmdArg['cold'];
    $executeEach = $cmdArg['each'] ?? false;

    $hasSummary = ! empty($summaryType);
    $hasNative = ! empty($native);

    $common = (include __DIR__ . '/common.php');
    $basePath = getBenchmarkBasePath();

    $dbCollection = $dataSet->getGroup() . '_' . $dataSet->getRules()[0];
    $dataBasePath = $dataSet->dataSetPath();

    $outDir = $common['bench.output.dir'];

    if ($executeEach)
        $outDir = "each-$outDir";

    $outDir = "$rules-$cmd-$outDir";

    if ($hasNative)
        $outDir .= "+native-$native";
    if ($hasRules)
        $outDir .= "+rules-$rules";
    if ($hasSummary)
        $outDir .= "+summary-$summaryType";
    if ($cold)
        $outDir .= '+cold';
        
    $outputPath = "${common['bench.output.base.path']}/$group";
    $summaryFileName = "summary-$summaryType.txt";

    $common['bench.output.dir'] = $outDir;
    $coldS = $cold ? '-cold' : '';

    $javaProperties = selectJavaProperties($cmdArg);

    $ret = array_merge_recursive($common, [
        'app.cmd' => $cmd,
        'bench.query.native.pattern' => $hasNative ? "$dataBasePath/queries/%s_each-native-$native.txt" : '',
        'bench.cold' => $cold,
        'dataSet' => $dataSet
    ]);
    $ret['java.properties'] = array_merge($ret['java.properties'], [
        'db.collection' => $dbCollection,
        'summary.type' => $summaryType,
        'queries.dir' => "$basePath/benchmark/queries",
        'querying.each' => $executeEach ? 'y' : 'n',
        'output.path' => "$outputPath/$outDir",
        'rules' => '',
        'summary' => ''
    ] + $javaProperties);

    if ($hasRules)
        $ret['java.properties'] = array_merge($ret['java.properties'], [
            'rules' => $dataSet->rulesPath(),
            'summary' => $hasSummary ? "$dataBasePath/$summaryFileName" : null
        ]);
    return $ret;
}

function useQuery(array $config, string $query): array
{
    $config["query.native"] = sprintf($config["query.native"], $query);
    return $config;
}
