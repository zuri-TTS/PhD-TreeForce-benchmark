<?php
require_once __DIR__ . '/classes/autoload.php';
require_once __DIR__ . '/common/functions.php';

array_shift($argv);

$commonDateFormat = (include __DIR__ . '/benchmark/config/common.php')['datetime.format'];

while (! empty($exec = \parseArgvShift($argv, ";"))) {
    $testDir = \argShift($exec, 'output');
    $time_s = \argShift($exec, 'after', null);
    $cmd = \argShift($exec, 'cmd', 'show');

    \wdPush($testDir);

    if (empty($time_s))
        throw new \Exception("A time must be given");

    $time = new \DateTime($time_s);
    $plotFiles = [];

    $dirs = \scandirNoPoints();
    $groups = [];

    foreach ($dirs as $dir) {
        $delements = \Help\Plotter::extractDirNameElements($dir);
        $groups[$delements['full_pattern']][$dir] = $delements;
    }
    unset($dirs);

    $partitioning = [];

    foreach ($groups as $group) {
        if (count($group) == 1)
            continue;

        $partitioning[] = \array_partition($group, function ($v, $k) use ($time, $commonDateFormat) {
            $dtime = \DateTime::createFromFormat($commonDateFormat, $v['time']);
            return $time < $dtime;
        }, ARRAY_FILTER_USE_BOTH);
    }
    unset($groups);

    // Check pb
    $error = false;

    foreach ($partitioning as &$partitions) {
        $partitions = \array_map(fn ($p) => \array_keys($p), $partitions);

        if (count($partitions[1]) > 1) {
            fputs(STDERR, "Error: more than one destination dir");
        }
        $partitions[1] = $partitions[1][0];
    }
    unset($partitions);

    if ($error)
        throw new \Exception();

    foreach ($partitioning as $partitions) {
        $dest = $partitions[1];

        foreach ($partitions[0] as $src) {
            \wdPush($src);

            if ($cmd === 'show')
                echo "\nsrc $src\n";

            $files = \glob("*.csv");
            $files = \array_filter($files, fn ($f) => $f[0] !== '@');

            foreach ($files as $f) {
                $newFile = "../$dest/$f";

                if ($cmd === 'show')
                    echo "=> $newFile\n";
                elseif ($cmd === 'copy')
                    \copy($f, $newFile);
            }
            \wdPop();

            if ($cmd === 'drop')
                \rrmdir($src);
        }
    }
    \wdPop();
}

// ====================================================================
