<?php
return [
    'id' => 'time',
    'process' => 'all',
    'terminal.size' => '1500,500',
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
    'plot' => function (array $val): void {
        $ls = 1;

        $yMax = $val['time.real.max'];
        $yNbLine = log10($yMax);

        for ($i = 0, $m = 1; $i < $yNbLine; $i ++) {
            $lines[] = "$m ls 0";
            $m *= 10;
        }
        $lines = implode(",\\\n", $lines);

        foreach ($val['files'] as $f) {
            $fname = $f['file.name'];
            $tmp[] = <<<EOD
            '{$f['data.file.name']}' u 2:xtic(1) title '$fname real' ls $ls fs pattern 0 \\
            ,'' u 3 title '$fname cpu' fs pattern 3 ls $ls \\\n
            EOD;
            $ls ++;
        }
        echo "plot $lines, \\\n", implode(',', $tmp), "\n";
    }
];
