<?php
array_shift($argv);

require_once __DIR__ . '/classes/autoload.php';
require_once __DIR__ . '/common/functions.php';

$modelPath = 'depth';

$ranges = [
    [
        5, // depth min
        13, // max
        1, // increment
        1, // nb branch min
        1, // max
        10 // nb queries
    ]
];

$args = \parseArgv($argv);

foreach ($ranges as $range) {
    list ($dmin, $dmax, $step, $bmin, $bmax, $nb) = $range;

    for (; $dmin < $dmax; $dmin += $step) {

        $args = [
            'depth_min' => $dmin,
            'depth_max' => $dmin,
            'branches_min' => $bmin,
            'branches_max' => $bmax,
            'nb' => $nb,
            'keep_value_proba' => 0,
            'display' => false
        ] + $args;
        $generator = new \Generator\ModelGen($modelPath, $args, 'queries');
        $generator->generate();
    }
}