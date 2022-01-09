<?php
include_once __DIR__ . '/xmark_to_json/XMark2Json.php';
include_once __DIR__ . '/common/functions.php';

\array_shift($argv);

if (\count($argv) == 0) {
    echo "Convert ALL dataSets to json\n\n";
    $argv = getDataSetGroups();
}

while (null !== ($arg = \array_shift($argv))) {
    $tmp = explode('/', $arg);

    if (\count($tmp) === 2) {
        $converter = new \XMark2Json($tmp[0]);
        $converter->convert($tmp[1]);
    } else {
        $converter = new \XMark2Json($arg);
        $converter->convert();
    }
}
