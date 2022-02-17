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

function getDefaultJavaProperties(): array
{
    return (include __DIR__ . '/common.php')['java.properties'];
}

function shiftJavaProperties(array &$args): array
{
    $ret = getDefaultJavaProperties();

    foreach ($cp = $args as $k => $v) {
        if (! is_string($k))
            continue;
        if ($k[0] !== 'P')
            continue;

        $prop = substr($k, 1);
        if (! \array_key_exists($prop, $ret))
            continue;
        if (is_bool($v))
            $v = $v ? 'y' : 'n';

        $ret[$prop] = $v;
        unset($args[$k]);
    }
    return $ret;
}

function makeConfig(DataSet $dataSet, array $cmdArg, array $javaProperties) //
{
    $group = $dataSet->group();
    $rules = $dataSet->rules();
    $dataSetPath = $dataSet->path();

    $cmd = $cmdArg['cmd'];
    $cold = $cmdArg['cold'];
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

    if ($hasSummary) {
        $summaryFileName = "summary-$summaryType.txt";
        $summaryPath = "$dataSetPath/$summaryFileName";
    }

    $common = (include __DIR__ . '/common.php');
    $basePath = getBenchmarkBasePath();

    $javaProperties = array_merge([
        'db.collection' => MongoImport::getCollectionName($dataSet),
        'summary.type' => $summaryType,
        'queries.dir' => \Queries::getBasePath(),
        'rules' => '',
        'summary' => $summaryPath ?? '',
        'toNative.useSummary' => ($cmd !== 'summarize' && $dataSet->isSimplified()) ? 'y' : 'n'
    ], $javaProperties) + $common['java.properties'];

    $outputDirGenerator = $common['bench.output.dir.generator'];
    $outDirPattern = $outputDirGenerator($dataSet, $cmdArg, $javaProperties);

    $outDir = sprintf($outDirPattern, $common['bench.datetime']->format('Y-m-d H:i:s v'));

    $outputPath = \realpath($cmdArg['output'] ?? $common['bench.output.base.path']) . "/$outDir";
    $javaProperties['output.path'] = $outputPath;

    $ret = array_merge($common, [
        'app.cmd' => $cmd,
        'bench.query.native.pattern' => $hasNative ? "$dataSetPath/queries/%s_each-native-$native.txt" : '',
        'bench.cold' => $cold,
        'dataSet' => $dataSet,
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

function useQuery(array $config, string $query): array
{
    $config["query.native"] = sprintf($config["query.native"], $query);
    return $config;
}
