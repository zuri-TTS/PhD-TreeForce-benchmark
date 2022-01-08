<?php
$config_default = include __DIR__ .'/../config-default.php';
$baseDir = $config_default['baseDir'];

return [
    'rules.q.path' => "${baseDir}/benchmark/rules/querying.txt",
    'rules.r.path' => "${baseDir}/benchmark/rules/reasoning.txt",
    'rules.model.path' => "${baseDir}/benchmark/rules_model.txt"
    ];
