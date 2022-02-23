
<?php
require_once __DIR__ . '/classes/autoload.php';
include_once __DIR__ . '/common/functions.php';

\array_shift($argv);

$args = new class() extends stdClass {

    public string $target = '';

    public string $mode = 'rules';
};
$cmdArgsDef = [];

$cmdParsed = \parseArgvShift($argv);
$oargs = (new \Args\ObjectArgs($args));
$oargs->updateAndShift($cmdParsed);
$groups = \array_filter_shift($cmdParsed, 'is_int', ARRAY_FILTER_USE_KEY);

$oargs->checkEmpty($cmdParsed);

$groups = \array_unique(DataSets::allGroups($groups));
DataSets::checkGroupsNotExists($groups, false);

if (empty($args->target)) {

    foreach ($groups as $group) {
        $link = "benchmark/{$args->mode}_conf/symlinks/$group";

        if (\is_link($link))
            $target = \readlink($link);
        else
            $target = '??';

        echo "$link --> $target\n";
    }
} else {

    foreach ($groups as $group) {
        $link = "benchmark/{$args->mode}_conf/symlinks/$group";
        $target = \str_replace('{}', $group, $args->target);

        echo "$link --> $target\n";

        if (! \is_dir($target)) {
            fwrite(STDERR, "WARNING! $target is not a directory\n");
            continue;
        }
        $target = \realpath($target);

        if (\is_link($link))
            \unlink($link);

        \symlink($target, $link);
    }
}
