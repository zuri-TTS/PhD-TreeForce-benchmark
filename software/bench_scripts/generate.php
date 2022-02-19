<?php
array_shift($argv);

include __DIR__ . '/classes/autoload.php';
include __DIR__ . '/common/functions.php';

$cmdArgsDef = [
    'mode' => '',
    'model' => ''
];
$cmdParsed = $cmdArgsDef;
$cmdRemains = \updateArray_getRemains(\parseArgvShift($argv), $cmdParsed);

if (empty($cmdParsed['mode']) || empty($cmdParsed['model'])) {
    $usage = "\nValid cli arguments are:\n" . \var_export($cmdParsed, true) . "\n";
    fwrite(STDERR, $usage);
    throw new \Exception("'mode' and 'model' must be set:\n" . \var_export($cmdRemains, true));
}

$generator = new \Generator\ModelGen($cmdParsed['model'], $cmdRemains, $cmdParsed['mode']);
$generator->generate();
