
<?php
require_once __DIR__ . '/classes/autoload.php';
include_once __DIR__ . '/common/functions.php';

\array_shift($argv);

$args = new class() extends stdClass {

    public string $target = '';
    
    public string $mode = 'rules';
};
$cmdArgsDef = [];

if (empty($argv))
    $argv[] = ";";

$cmdParsed = \parseArgvShift($argv);
$oargs = (new \Args\ObjectArgs($args));
$oargs->updateAndShift($cmdParsed);
$groups = \array_filter_shift($cmdParsed, 'is_int', ARRAY_FILTER_USE_KEY);

$oargs->checkEmpty($cmdParsed);

if (empty($args->target))
    throw new \Exception("Argument 'target' must be set");
if (! \is_dir($args->target))
    throw new \Exception("$args->target is not a directory");

if (\count($groups) == 0) {
    $groups = [
        null
    ];
}
$groups = \array_unique(DataSets::allGroups($groups));
DataSets::checkGroupsNotExists($groups, false);
$target = \realpath($args->target);

foreach ($groups as $group) {
    $link = "benchmark/{$args->mode}_conf/symlinks/$group";
    
    echo "$link --> $target\n";
    
    if(\is_link($link))
        \unlink($link);
    
    \symlink($target, $link);
}
