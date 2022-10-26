set terminal pngcairo size 1000,500
set key outside below horizontal maxcols 2
<?php
$plotConfig = $PLOTTER->plot_getConfig();

// Define what to plot
{
    $xPointPos = 2;
    $yPointPos = 3;
}

$timeDiv = $plotConfig['measure.div'];
$formatY = $plotConfig['plot.format.y'];

$xRangeMax = $plotConfig['plot.xrange.max'] ?? null;
$yRangeMax = $plotConfig['plot.yrange.max'] ?? '*';
$yRangeMin = $plotConfig['plot.yrange.min'] ?? '*';

echo "set yrange [$yRangeMin:$yRangeMax]\n";

if ($logscale = ($plotConfig['logscale'] ?? null))
    echo "set logscale y {$logscale}\n";

$yloglabel = $logscale ? ' (log)' : null;

$userCommands = (array) ($plotConfig['gnuplot.commands'] ?? null);
$userCommands = \implode("\n", $userCommands);

echo <<<EOD
set xrange [0:$xRangeMax]
set xlabel "{$plotConfig['xlabel']}"
set ylabel "{$plotConfig['ylabel']}$yloglabel"
set format y "$formatY"
tm(x)=x/($timeDiv)
$userCommands

EOD;

$lines = [];
$points = [];
$interpolate = [];
$i = 1;
$fit = "";

// Search if multiple dataset are used
{
    $groups = \array_keys($PLOTTER->getCsvGroups());
    $names = $parts = [];

    foreach ($groups as $g) {
        $elements = \Help\Plotter::extractDirNameElements($g);
        $names[] = $elements['group'];
        $parts[] = $elements['partitioning'];
    }
    $names = \array_unique($names);
    $parts = \array_unique($parts);
    $hasMultipleDataset = \count($names) > 1;
    $hasMultiplePartitioning = \count($parts) > 1;
}

$selection = [
    'group',
    'parallel',
    'summary'
];

$csvGroups = $PLOTTER->getCsvGroups();
$allGroupsName = \array_keys($csvGroups);

$fstyle = function ($configParam, int $i, string $groupName) use ($plotConfig, $allGroupsName) {
    $style = $plotConfig[$configParam];

    if (\is_callable($style))
        return $style($groupName, $allGroupsName, $i);

    $styleReplacement = [
        'lc' => $i,
        'lt' => $i
    ];
    return \str_format($style, $styleReplacement);
};

foreach ($csvGroups as $group => $csvPaths) {
    $csvData = $PLOTTER->getCsvData(\Help\Arrays::first($csvPaths));
    $dirName = \basename(\dirname(\Help\Arrays::first($csvPaths)));
    $delements = \Help\Plotter::extractDirNameElements($dirName);
    $sdelements = \Help\Arrays::subSelect($delements, $selection);

    $titleRef = \Plotter\AbstractFullStrategy::makeXTic_fromDirName(\Help\Plotter::encodeDirNameElements($sdelements, ''));

    if ($hasMultiplePartitioning || ($plotConfig['display.partitioning'] ?? false)) {
        $titleRef = "{$delements['partitioning']} $titleRef";
    }
    if ($hasMultipleDataset || ($plotConfig['display.group'] ?? false)) {
        $delim = $hasMultiplePartitioning ? '.' : ' ';
        $titleRef = "{$delements['group']}$delim$titleRef";
    }

    $titleRef = "\"$titleRef\"";

    $notitle = false;

    if ($plotConfig['plot.points']) {
        $title = $notitle ? "notitle" : "title $titleRef";
        $style = $fstyle('plot.points.style', $i, $group, $allGroupsName);

        $points[] = "'$group.dat' u $xPointPos:(tm(\${$yPointPos})) with points $title $style";
        $notitle = true;
    }

    if ($plotConfig['plot.lines']) {
        $title = $notitle ? "notitle" : "title $titleRef";
        $style = $fstyle('plot.lines.style', $i, $group, $allGroupsName);

        $lines[] = "'$group.dat' u $xPointPos:(tm(\${$yPointPos})) with lines $title $style";
        $notitle = true;
    }

    if ($plotConfig['plot.fit.linear']) {
        $title = $notitle ? "notitle" : "title $titleRef";
        $style = $fstyle('plot.fit.linear.style', $i, $group, $allGroupsName);

        echo "f$i(x) = a$i + b$i*x\n";
        echo "fit f$i(x) '$group.dat' u $xPointPos:(tm(\${$yPointPos})) via a$i,b$i\n";

        $interpolate[] = "f$i(x) $title $style";
    }
    $i ++;
}
$tmp = \array_merge($interpolate, $lines, $points);

echo "plot ", implode(",\\\n", $tmp), "\n";
