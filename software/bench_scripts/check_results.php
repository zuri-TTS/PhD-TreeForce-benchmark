<?php
require_once __DIR__ . '/classes/autoload.php';
require_once __DIR__ . '/common/functions.php';

array_shift($argv);

$cmdArgsDef = [
    'mode' => 'stats',
    'file' => false
];

$cmdParsed = $cmdArgsDef;
$cmdRemains = \updateArray_getRemains(\parseArgvShift($argv), $cmdParsed);
$paths = \array_filter_shift($cmdRemains, 'is_int', ARRAY_FILTER_USE_KEY);

if (! empty($cmdRemains)) {
    $usage = "\nWarning; Valid cli arguments are:\n" . \var_export($cmdParsed, true) . "\n";
    fwrite(STDERR, $usage);
    throw new \Exception("Unknown cli argument(s):\n" . \var_export($cmdRemains, true));
}
$checker = new \Check($paths);
$outFile = ! empty($cmdParsed['file']) && \count($paths) === 1;

if ($outFile)
    \ob_start();

switch ($mode = $cmdParsed['mode']) {
    case 'reformulations.nb':
        $mode = 'reformulations_nb';
    case 'reformulations_nb':
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

if ($outFile) {
    $out = \ob_get_clean();
    echo $out;

    $fpath = $paths[0];
    $fpath = "$fpath/$mode.txt";

    echo "\nWriting $fpath\n";
    file_put_contents($fpath, $out);
}
// ====================================================================
