<?php
require_once __DIR__ . '/classes/autoload.php';
include_once __DIR__ . '/gnuplot/functions.php';
include_once __DIR__ . '/gnuplot/Plot.php';
require_once __DIR__ . '/common/functions.php';

array_shift($argv);

while (! empty($exec = \parseArgvShift($argv, ";"))) {
    $outPath = \argShift($exec, 'output');
    $config = \argShift($exec, 'config', __DIR__ . '/gnuplot/config.php');
    $paths = \array_filter($argv, 'is_int');

    $plotFiles = [];

    if (empty($paths))
        $paths = (array)$outPath;

    foreach ($paths as $outPath) {
        
        if (is_dir($outPath)) {
            $dir = new RecursiveDirectoryIterator($outPath);
            $ite = new RecursiveIteratorIterator($dir);
            $reg = new RegexIterator($ite, "#/[^@][^/]+\.csv$#");

            foreach ($reg as $file)
                $plotFiles[] = $file->getRealPath();
        } elseif (is_file($outPath) && preg_match('#\.csv$#', $outPath)) {
            $plotFiles = [
                $outPath
            ];
            $outPath = \dirname($outPath);
        } else {
            fputs(STDERR, "Can't handle '$outPath'!\n");
            continue;
        }
    }
    $queries = [];
    $allNbThreads = [];
    (new \Plot($outPath, $plotFiles))->plot(include $config);
}

// ====================================================================
