<?php
include __DIR__ . '/main_bench/Benchmark.php';

array_shift($argv);

if (empty($argv))
{
    \fputs(STDERR, "No config file for input");
    exit(1);
}

foreach ($argv as $config) {
    $config = include $config;
    $bench = new Benchmark($config);
    $bench->executeOnce();
}
