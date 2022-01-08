<?php
return [
    'id' => 'time',
    'process' => 'all',
    'template.path' => __DIR__ . '/templates/all.plot.php',
    'data.plot' => function (array $val): void {
        echo "measure real cpu\n";

        foreach (\array_filter($val['data'], 'Plot::isTimeMeasure') as $group => $measure) {
            $r = max(0, $measure['r']);
            $c = max(0, $measure['c']);
            $realRemain = max(0, $r - $c);
            echo "\"$group\" $r $c\n";
        }
    },
    'plot' => function (Plot $plot): void {
        $plot_lines = $plot->getPlotYLines();
        $val = $plot->getPlotVariables();
        $ls = 1;

        foreach ($val['files'] as $f) {
            $fname = $f['file.name'];
            $tmp[] = <<<EOD
            '{$f['data.file.name']}' u 2:xtic(1) title '$fname real' ls $ls fs pattern 0 \\
            ,'' u 3 title '$fname cpu' fs pattern 3 ls $ls \\\n
            EOD;
            $ls ++;
        }
        echo "plot $plot_lines\\\n,", implode(',', $tmp), "\n";
    }
];
