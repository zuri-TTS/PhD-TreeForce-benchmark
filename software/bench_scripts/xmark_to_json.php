<?php
include_once __DIR__ . '/xmark_to_json/XMark2Json.php';
include_once __DIR__ . '/common/functions.php';

\array_shift($argv);

if (\count($argv) == 0) {
    echo "Convert ALL dataSets to json\n\n";
    $argv = getDataSetGroups();
}

$cmdArgsDef = [
    'clean' => false
];

$cmdArgs = \parseArgv($argv);

while (! empty($argv)) {
    $cmdParsed = \parseArgvShift($argv, ';') + $cmdArgsDef;
    $dataSets = \array_filter($cmdParsed, 'is_int', ARRAY_FILTER_USE_KEY);

    while (null !== ($arg = \array_shift($dataSets))) {
        $tmp = explode('/', $arg);

        $method = $cmdParsed['clean'] ? 'delete' : 'convert';

        if (\count($tmp) === 2) {
            $converter = new \XMark2Json($tmp[0]);
            $converter->$method($tmp[1]);
        } else {
            $converter = new \XMark2Json($arg);
            $converter->$method();
        }
    }
}