<?php
include_once __DIR__ . '/xmark_to_json/XMark2Json.php';
include_once __DIR__ . '/common/functions.php';

array_shift($argv);
$dataSet = array_shift($argv);

$converter = new XMark2Json($dataSet);
$converter->convert();
    