<?php
include_once __DIR__ . '/xmark_to_json/XMark2Json.php';
include_once __DIR__ . '/common/functions.php';
include_once __DIR__ . '/classes/DataSet.php';

\array_shift($argv);

$cmdArgsDef = [
    'clean' => false
];

while (! empty($argv)) {
    $cmdParsed = \parseArgvShift($argv, ';') + $cmdArgsDef;
    $dataSets = \array_filter($cmdParsed, 'is_int', ARRAY_FILTER_USE_KEY);

    if (\count($dataSets) == 0) {
        echo "Convert ALL dataSets to json\n\n";
        $dataSets = DataSet::getAllGroups();
    }

    while (null !== ($dataSetId = \array_shift($dataSets))) {
        echo "\n";
        $dataSet = new DataSet($dataSetId);

        $method = $cmdParsed['clean'] ? 'delete' : 'convert';

        $converter = new \XMark2Json($dataSet);
        $converter->$method();
    }
}