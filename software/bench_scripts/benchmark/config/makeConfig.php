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
    $rules = $dataSet->getTheRules();
    $dataSetPath = $dataSet->dataSetPath();

    $cmd = $cmdArg['cmd'];
    $cold = $cmdArg['cold'];
    $summaryType = $cmdArg['summary'] ?? '';

    if ($rules === 'original') {
        $hasRules = false;
        $native = '';
        $hasSummary = false;
    } else {
        $hasRules = true;
        $native = $cmdArg['native'] ?? '';
        $hasSummary = ! empty($summaryType);
    }
    $hasNative = ! empty($native);

    if ($hasSummary) {
        $summaryFileName = "summary-$summaryType.txt";
        $summaryPath = "$dataSetPath/$summaryFileName";

        if (! \is_file($summaryPath) && ! \in_array($cmd, [
            "summarize",
            "config"
        ]))
            throw new \Exception("Summary '$summaryPath' does not exists");
    }

    $common = (include __DIR__ . '/common.php');
    $basePath = getBenchmarkBasePath();

    $javaProperties = selectJavaProperties($cmdArg);
    $javaProperties = array_merge([
        'db.collection' => MongoImport::getCollectionName($dataSet),
        'summary.type' => $summaryType,
        'queries.dir' => "$basePath/benchmark/queries",
        'rules' => '',
        'summary' => $summaryPath ?? ''
    ], $javaProperties) + $common['java.properties'];

    $outputDirGenerator = $common['bench.output.dir.generator'];
    $outDirPattern = $outputDirGenerator($dataSet, $cmdArg, $javaProperties);

    $outDir = sprintf($outDirPattern, $common['bench.datetime']->format('Y-m-d H:i:s v'));

    $outputPath = ($cmdArg['output'] ?? $common['bench.output.base.path']) . "/$outDir";
    $javaProperties['output.path'] = "$outputPath";

    $ret = array_merge($common, [
        'app.cmd' => $cmd,
        'bench.query.native.pattern' => $hasNative ? "$dataSetPath/queries/%s_each-native-$native.txt" : '',
        'bench.cold' => $cold,
        'dataSet' => $dataSet,
        'bench.output.dir' => $outDir,
        'bench.output.pattern' => $outDirPattern,
    ]);
    $ret['java.properties'] = $javaProperties;

    if ($hasRules)
        $ret['java.properties'] = array_merge($ret['java.properties'], [
            'rules' => $dataSet->theRulesPath()
        ]);
    return $ret;
}

function useQuery(array $config, string $query): array
{
    $config["query.native"] = sprintf($config["query.native"], $query);
    return $config;
}
