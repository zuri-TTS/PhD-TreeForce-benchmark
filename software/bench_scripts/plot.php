<?php
require_once __DIR__ . '/classes/autoload.php';
include_once __DIR__ . '/gnuplot/functions.php';
include_once __DIR__ . '/gnuplot/Plot.php';
require_once __DIR__ . '/common/functions.php';

array_shift($argv);

while (! empty($exec = \parseArgvShift($argv, ";"))) {
    $outPath = \argShift($exec, 'output');
    $types = \argShift($exec, 'types', '');
    $paths = \array_filter($argv, 'is_int');
    $config = __DIR__ . '/gnuplot/config.php';

    if (! empty($types))
        $types = \explode(',', $types);

    $plotFiles = [];

    if (empty($paths))
        $paths = (array) $outPath;

    foreach ($paths as $outPath) {

        if (is_dir($outPath)) {
            $dir = new RecursiveDirectoryIterator($outPath);
            $ite = new RecursiveIteratorIterator($dir);
            $ite->setMaxDepth(1);
            $reg = new RegexIterator($ite, "#/[^@][^/]*\.csv$#");

            foreach ($reg as $file) {
                $csv = $file->getRealPath();

                if (! str_starts_with(\basename(\dirname($csv)), "full_"))
                    $plotFiles[] = $csv;
            }
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
    $config = include $config;

    if (! empty($types)) {
        $delTypes = \array_diff(\array_keys($config['plotter.factory']), $types);

        foreach ($delTypes as $d)
            unset($config['plotter.factory'][$d]);
    }
    (new \Plot($outPath, $plotFiles))->plot($config);
}

// ====================================================================
