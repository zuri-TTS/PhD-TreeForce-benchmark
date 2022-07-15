<?php
require_once __DIR__ . '/classes/autoload.php';
include_once __DIR__ . '/gnuplot/functions.php';
include_once __DIR__ . '/gnuplot/Plot.php';
require_once __DIR__ . '/common/functions.php';

array_shift($argv);

while (! empty($exec = \parseArgvShift($argv, ";"))) {
    $types = \argShift($exec, 'types', '');
    $paths = \array_filter($exec, 'is_int', ARRAY_FILTER_USE_KEY);

    $config = __DIR__ . '/gnuplot/config.php';

    if (! empty($types))
        $types = \explode(',', $types);

    $types = \array_unique($types);

    $config = include $config;

    // Drop unused factories
    if (! empty($types)) {
        $delTypes = \array_diff(\array_keys($config['plotter.factory']), $types);

        foreach ($delTypes as $d)
            unset($config['plotter.factory'][$d]);
    }

    foreach ($paths as $outPath) {
        (new \Plot($outPath))->plot($config);
    }
}

// ====================================================================
