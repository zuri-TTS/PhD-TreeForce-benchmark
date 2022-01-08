<?php
array_shift($argv);
$configFile = array_shift($argv);

include __DIR__ . '/generate_rules/functions.php';

if (empty($configFile))
    $configs = include __DIR__ . '/generate_rules/config.php';
else
    $configs = include $configFile;

$configDefault = $configs['default'] ?? [];
unset($configs['default']);

// If not a multiple configuration
if (! isset($configs[0]))
    $configs = [
        $configs
    ];

$baseDir = $config['baseDir'] ?? \getcwd();

if(!\chdir($baseDir))
    exit(1);

foreach ($configs as $config) {
    $config += $configDefault;
    generateRules($config);
}
