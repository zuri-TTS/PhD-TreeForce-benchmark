<?php
require_once __DIR__ . '/classes/autoload.php';
require_once __DIR__ . '/common/functions.php';

array_shift($argv);

while (! empty($exec = \parseArgvShift($argv, ";"))) {
    $outPath = \argShift($exec, 'output');
    $paths = \array_filter($argv, 'is_int');
    $config = __DIR__ . '/gnuplot/config.php';

    if (empty($paths))
        $paths = (array) $outPath;

    foreach ($paths as $outPath) {

        if (is_dir($outPath)) {
            $dir = new RecursiveDirectoryIterator($outPath);
            $ite = new RecursiveIteratorIterator($dir);
            $reg = new RegexIterator($ite, "#/[^@][^/]*\.csv$#");

            foreach ($reg as $file) {
                $query = \basename($file, '.csv');
                $bname = \basename(\dirname($file));
                \preg_match("#^\[(.+)\]#U", $bname, $matches);
                $dataGroup = $matches[1];

                $k = "{$dataGroup}_$query";
                $csvGroups[$k][] = $file->getRealPath();
            }
        } else {
            fputs(STDERR, "Can't handle '$outPath'!\n");
            continue;
        }
    }
    \uksort($csvGroups, 'strnatcasecmp');

    foreach ($csvGroups as &$files)
        \natcasesort($files);
    unset($files);

    foreach ($csvGroups as $group => $files) {
        echo "$group: ";
        $ok = true;
        $answers = null;
        $cache = [];

        foreach ($files as $file) {
            $data = CSVReader::read($file);
            $ans = (int) $data['answers']['total'];
            $cache[$file] = $ans;

            if (null === $answers)
                $answers = $ans;
            elseif ($answers !== $ans) {
                $ok = false;
            }
        }

        if ($ok)
            echo "$answers answers\n";
        else {
            echo "Failed\n";

            foreach ($cache as $f => $ans)
                echo "$f: $ans\n";

            echo "\n";
        }
    }
}

// ====================================================================
