<?php

function makeConfig(string $dataSet, string $ruleSetPath, int $seed)
{
    $common = (include __DIR__ . '/common.php');
    $baseDir = $common['baseDir'];
    $ret = $common + [
        'data.path' => "$baseDir/benchmark/data/$dataSet",
        'xmark.file.path' => "$baseDir/benchmark/data/$dataSet/xmark.xml",
        'noised.json.postprocess' => fn($config) => (include __DIR__ . '/../../software/bench_scripts/xmark_to_json/json_postprocess-random_keys.php')($config, $seed),
        //         'rules.path' => "$baseDir/benchmark/$ruleSetPath"
    ];
    return $ret;
}
