<?php

function selectJavaProperties(array $cmdArg)
{
    $ret = [];

    foreach ($cmdArg as $k => $v) {
        if ($k[0] !== 'P')
            continue;

        $ret[substr($k, 1)] = $v;
    }
    return $ret;
}

function makeConfig(array $cmdArg) //
{
    if ($cmdArg['data'] === 'original') {
        $dataType = 'original';
        $hasRules = false;
        $summaryType = "";
        $native = '';
    } else {
        $dataType = 'noised';
        $hasRules = $cmdArg['rules'];
        $summaryType = ! empty($hasRules) ? $cmdArg['summary'] : "";
        $native = $cmdArg['native'] ?? '';
    }
    $dataSet = $cmdArg['dataSet'].'/'.$dataType;
    $cmd = $cmdArg['cmd'];
    $cold = $cmdArg['cold'];
    // $checkTerminalLeaf = $cmdArg['db.checkTerminal'];
    $executeEach = $cmdArg['each'] ?? false;
    $data = "$dataSet/${cmdArg['data']}";

    $hasSummary = ! empty($summaryType);
    $hasNative = ! empty($native);

    $common = (include __DIR__ . '/common.php');
    $basePath = $common['java.properties']['base.path'];

    $dataSet = rtrim($dataSet, '/');

    $outputPath = "${common['bench.output.base.path']}/$dataSet";
    $dbCollection = str_replace('/', '_', $dataSet);
    $dataBasePath = "$basePath/benchmark/data/$dataSet";

    $outDir = $common['bench.output.dir'];

    if ($executeEach)
        $outDir = "each-$outDir";

    $outDir = "$cmd-$outDir";

    if ($hasNative)
        $outDir .= "+native-$native";
    if ($hasRules)
        $outDir .= "+rules";
    if ($hasSummary)
        $outDir .= "+summary-$summaryType";
    // if (! $dbCheckTerminalLeaf)
    // $outDir .= "+noTermLeaf";
    if ($cold)
        $outDir .= '+cold';

    $summaryFileName = "summary-$summaryType.txt";

    $common['bench.output.dir'] = $outDir;
    $coldS = $cold ? '-cold' : '';

    $javaProperties = selectJavaProperties($cmdArg);

    $ret = array_merge_recursive($common, [
        'app.cmd' => $cmd,
        'bench.query.native.pattern' => $hasNative ? "$dataBasePath/queries/%s_each-native-$native.txt" : '',
        'bench.cold' => $cold
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
            'rules' => "$dataBasePath/rules",
            'summary' => $hasSummary ? "$dataBasePath/$summaryFileName" : null
        ]);
    return $ret;
}

function useQuery(array $config, string $query): array
{
    $config["query.native"] = sprintf($config["query.native"], $query);
    return $config;
}
