<?php

function makeConfig(DataSet $dataSet, $partitions, array &$cmdArg, array $javaProperties) //
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
    $fpartition = function ($partition): string {
        $range = $partition->getLogicalRange();

        if (empty($range))
            $range = '';
        else {
            $range = implode('..', $range);
            $range = ";[$range]";
        }
        return $partition->getID() . ';' . $partition->getPrefix() . $range;
    };

    if ($cmdArg['parallel']) {

        if (! \is_array($partitions))
            throw new \Exception("In parallel mode \$collections must be an array; have $partitions");

        $cprefix = "";
        $javaCollection = [];
        $javaSummary = [];
        $benchSummary = [];
        $javaToNativeSummary = [];
        $javaPartition = [];

        foreach ($partitions as $partition) {
            $partID = $partition->getID();
            $cprefix = empty($partID) ? '' : "$partID-";

            $javaCollection[] = $partition->getCollectionName();
            $javaSummary[] = $fsummary('${dataset.baseDir}', $cprefix, '${summary.type}');
            $javaToNativeSummary[] = $fsummary('${dataset.baseDir}', $cprefix, '${toNative.summary.type}');
            $benchSummary[] = $fsummary($dataSetPath, $cprefix, $cmdArg['summary']);

            if ($partition->isLogical())
                $javaPartition[] = $fpartition($partition);
            else
                $javaPartition[] = '';
        }
        $benchToNativeSummary = '';
        $outDirPattern = $outputDirGenerator($dataSet, \Data\Partitions::noPartition(), $cmdArg, $javaProperties);
    } else {

        if (! $partitions instanceof \Data\IPartition)
            throw new \Exception("In sequential mode \$collections must be a \Data\IPartition; have " . print_r($partitions, true));

        $partition = $partitions;

        if ($partition->isLogical())
            $javaPartition = $fpartition($partition);

        $partID = $partition->getID();
        $cprefix = empty($partID) ? '' : "$partID-";
        $javaCollection = $partition->getCollectionName();

        if (empty($cmdArg['summary'])) {
            $javaSummary = '';
            $benchSummary = '';
        } else {
            $javaSummary = $fsummary('${dataset.baseDir}', $cprefix, '${summary.type}');
            $benchSummary = $fsummary($dataSetPath, $cprefix, $cmdArg['summary']);
        }

        if ($cmd === 'partition') {
            $javaToNativeSummary = $fsummary('${dataset.baseDir}', '', '${toNative.summary.type}');
            $benchToNativeSummary = $fsummary($dataSetPath, '', $cmdArg['toNative_summary']);
        } elseif (empty($cmdArg['toNative_summary'])) {
            $javaToNativeSummary = '';
            $benchToNativeSummary = '';
        } else {
            $javaToNativeSummary = $fsummary('${dataset.baseDir}', $cprefix, '${toNative.summary.type}');
            $benchToNativeSummary = $fsummary($dataSetPath, $cprefix, $cmdArg['toNative_summary']);
        }
        $outDirPattern = $outputDirGenerator($dataSet, $partition, $cmdArg, $javaProperties);
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

    // Note: for now only prefix partitions are presents
    if (isset($javaPartition)) {
        $javaProperties = \array_merge($javaProperties, [
            'partition' => $javaPartition,
            'partition.mode' => 'prefix',
            'partition.output.pattern' => '${dataset.baseDir}/partition.%s.txt'
        ]);
    }
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
