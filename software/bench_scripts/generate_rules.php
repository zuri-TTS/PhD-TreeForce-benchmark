<?php
array_shift($argv);

include __DIR__ . '/classes/autoload.php';
include __DIR__ . '/common/functions.php';
include __DIR__ . '/generate_rules/ModelGen.php';

$modelPath = \array_shift($argv);
$args = \parseArgv($argv);

$generator = new ModelGen($modelPath, $args);
$generator->generate() && $generator->generateRules();
