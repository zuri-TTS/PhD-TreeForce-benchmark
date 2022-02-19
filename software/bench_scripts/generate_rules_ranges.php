<?php
array_shift($argv);

require_once __DIR__ . '/classes/autoload.php';
require_once __DIR__ . '/common/functions.php';

$modelPath = 'genetic';

$ranges = [
    [
        2, // min
        10, // max
        1, // increment
        0 // result range
    ],
//     [
//         10,
//         100,
//         20,
//         0//10
//     ],
//     [
//         100,
//         550,
//         50,
//         0//25
//     ]
];

$options = [
    'Gsort' => 1,
    'generate' => true
];
$args = \parseArgv($argv) + $options;

foreach ($ranges as $range) {
    list ($i, $c, $s, $r) = $range;

    for (; $i < $c; $i += $s) {
        $max = $i + $r;

        $args['Qdefault'] = [
            $i,
            $max
        ];
        echo "\n[$i, $max]\n";
        $generator = new \Generator\ModelGen($modelPath, $args);
        $generator->generate();
    }
}