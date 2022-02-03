<?php
require_once __DIR__ . '/classes/autoload.php';
require_once __DIR__ . '/common/functions.php';
array_shift($argv);

$brings = [];

foreach ($argv as $arg) {
    if (\is_numeric($arg))
        $sleep = (int) $arg;
    else
        $brings[] = new BringIt($arg);
}

if (empty($brings))
    $brings[] = new BringIt('outputs');

if (!isset($sleep))
    $sleep = 1;

$sleep = (int) $sleep;

if ($sleep < 0)
    $sleep = 1;

if ($sleep === 0)
    foreach ($brings as $bring)
        $bring->scan();
else
    for (;;) {
        foreach ($brings as $bring)
            $bring->scan();
        sleep($sleep);
    }