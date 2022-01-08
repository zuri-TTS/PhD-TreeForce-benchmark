<?php
include_once __DIR__ . '/gnuplot/functions.php';
include_once __DIR__ . '/gnuplot/Plot.php';

array_shift($argv);

while (! empty($exec = nextExecution($argv, __DIR__ . '/gnuplot/config.php'))) {
    [
    $config,
    $plotPath
    ] = $exec;
    
    if (is_dir($plotPath)) {
        // $plotFiles = glob("$plotPath/*.csv");
        $dir = new RecursiveDirectoryIterator($plotPath);
        $ite = new RecursiveIteratorIterator($dir);
        $reg = new RegexIterator($ite, "#/[^@][^/]+\.csv$#");
        
        foreach ($reg as $file)
            $plotFiles[] = $file->getRealPath();
    } elseif (is_file($plotPath) && preg_match('#\.csv$#', $plotPath)) {
        $plotFiles = [
            $plotPath
        ];
        $plotPath = \dirname($plotPath);
    } else {
        fputs(STDERR, "Can't handle $plotPath!\n");
        continue;
    }
    $queries = [];
    $allNbThreads = [];
    (new \Plot($plotPath, $plotFiles))->plot(include $config);
}

// ====================================================================
