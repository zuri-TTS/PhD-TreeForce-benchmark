<?php
require_once __DIR__ . '/classes/autoload.php';
require_once __DIR__ . '/common/functions.php';

array_shift($argv);

$cmdArgsDef = [
    'mode' => 'stats'
];

$cmdParsed = $cmdArgsDef;
$cmdRemains = \updateArray_getRemains(\parseArgvShift($argv), $cmdParsed);
$paths = \array_filter_shift($cmdRemains, 'is_int', ARRAY_FILTER_USE_KEY);

if (! empty($cmdRemains)) {
    $usage = "\nWarning; Valid cli arguments are:\n" . \var_export($cmdParsed, true)."\n";
    fwrite(STDERR, $usage);
    throw new \Exception("Unknown cli argument(s):\n" . \var_export($cmdRemains, true));
}
$checker = new \Check($paths);

switch ($mode = $cmdParsed['mode']) {
    case 'reformulations_nb':
    case 'reformulations.nb':
        $checker->checkNbRefs();
        break;
    case 'stats':
        $checker->checkStats();
        break;
    case 'answers':
        $checker->checkNbAnswers();
        break;
    default:
        throw new \Exception("Invalid mode: $mode\n");
}

// ====================================================================
