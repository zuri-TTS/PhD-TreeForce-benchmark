<?php
$baseDir = \realpath(__DIR__ . "/../..");

// Set to true to relabel some data
return  [
    'baseDir' => $baseDir,
    'rules.path' => "${baseDir}/benchmark/rules/querying.txt",
    'unwind' => [
        'site.regions.$.*',
        'site.categories.*',
        'site.catgraph.*',
        'site.people.*',
        'site.open_auctions.*',
        'site.closed_auctions.*'
    ]
];
