<?php
return [
    'id' => 'time',
    'template.path' => __DIR__ . '/templates/simple.plot.php',
    'data.plot' => function (array $val): void {
        echo "measure real cpu\n";

        foreach (\array_filter($val['data'], 'Plot::isTimeMeasure') as $group => $measure) {
            $r = max(0, $measure['r']);
            $c = max(0, $measure['c']);
            $realRemain = max(0, $r - $c);
            echo "\"$group\" $r $c\n";
        }
    },
    'plot' => function (array $val): void {
        echo "plot '{$val['data.file.name']}' u 2:xtic(1) ls 1 fs pattern 0, '' u 3 fs pattern 3 ls 1";
    }
];
