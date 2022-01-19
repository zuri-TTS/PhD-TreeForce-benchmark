<?php
array_shift($argv);

include __DIR__ . '/common/functions.php';
include __DIR__ . '/generate_rules/ModelGen.php';

$modelPath = 'genetic';

$ranges = [
    [
        2,
        10,
        1
    ],
    [
        10,
        100,
        20
    ],
    [
        100,
        550,
        50
    ]
];

$options = [
    'Gsort' => 1,
    'generate' => true,
    'GnbLoops' => 500
];
$args = \parseArgv($argv) + $options;

foreach ($ranges as $range) {
    list ($i, $c, $s) = $range;

    for (; $i < $c; $i += $s) {
        $max = $i + (int) ($s / 2);

        $args['Qdefault'] = [
            $i,
            $max
        ];
        echo "\n[$i, $max]\n";
        $generator = new ModelGen($modelPath, $args);
        $generator->generate() && $generator->generateRules();
    }
}