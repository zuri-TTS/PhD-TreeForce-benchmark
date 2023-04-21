terminal="pngcairo"
set terminal terminal size 1000,500
set key outside below horizontal maxcols 2
<?php
$plotConfig = $PLOTTER->plot_getConfig();

$fmakeLegend = $plotConfig['plot.legend'] ?? null;

if (! \is_callable($fmakeLegend))
    $fmakeLegend = '\Help\Plotter::elementsSimpleFormat';

// Define what to plot
{
    $xPointPos = 2;
    $yPointPos = 3;
}

$timeDiv = $plotConfig['measure.div'];
$formatY = $plotConfig['plot.format.y'];

$yStep = $plotConfig['plot.yrange.step'] ?? null;
$xStep = $plotConfig['plot.xrange.step'] ?? null;

if ($xStep !== null)
    echo "set xtics $xStep\n";
if ($yStep !== null)
    echo "set ytics $yStep\n";

$xRangeMin = $plotConfig['plot.xrange.min'] ?? '*';
$xRangeMax = $plotConfig['plot.xrange.max'] ?? '*';
$yRangeMax = $plotConfig['plot.yrange.max'] ?? '*';
$yRangeMin = $plotConfig['plot.yrange.min'] ?? '*';

echo "set yrange [$yRangeMin:$yRangeMax]\n";

if ($logscale = ($plotConfig['logscale'] ?? null))
    echo "set logscale y {$logscale}\n";

$yloglabel = $logscale ? ' (log)' : null;

$userCommands = (array) ($plotConfig['gnuplot.commands'] ?? null);
$userCommands = \implode("\n", $userCommands);

echo <<<EOD
set xrange [$xRangeMin:$xRangeMax]
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
    $groups = \array_keys($PLOTTER->gettestGroups());
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
    'summary',
    'filter_prefix'
];

$testGroups = $PLOTTER->gettestGroups();
$allGroupsName = \array_keys($testGroups);

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

foreach ($testGroups as $groupName => $tests) {
    $dirName = \dirname(\Help\Arrays::first($tests));
    $delements = \Help\Plotter::extractDirNameElements($dirName);
    $sdelements = \Help\Arrays::subSelect($delements, $selection);

    // $titleRef = \Plotter\AbstractFullStrategy::makeXTic_fromDirName(\Help\Plotter::encodeDirNameElements($sdelements, ''));
    $titleRef = $fmakeLegend($delements);
    // $titleRef = \str_replace('||', 'parallel', $titleRef);

    // if ($hasMultiplePartitioning || ($plotConfig['display.partitioning'] ?? false)) {
    // $titleRef = "{$delements['partitioning']} $titleRef";
    // }
    if ($hasMultipleDataset || ($plotConfig['display.group'] ?? false)) {
        $delim = $hasMultiplePartitioning ? '.' : ' ';
        $titleRef = "{$delements['group']}$delim$titleRef";
    }

    $titleRef = "\"$titleRef\"";

    $notitle = false;

    if ($plotConfig['plot.points']) {
        $title = $notitle ? "notitle" : "title $titleRef";
        $style = $fstyle('plot.points.style', $i, $groupName, $allGroupsName);

        $points[] = "'$groupName.dat' u $xPointPos:(tm(\${$yPointPos})) with points $title $style";
        $notitle = true;
    }

    if ($plotConfig['plot.lines']) {
        $title = $notitle ? "notitle" : "title $titleRef";
        $style = $fstyle('plot.lines.style', $i, $groupName, $allGroupsName);

        $lines[] = "'$groupName.dat' u $xPointPos:(tm(\${$yPointPos})) with lines $title $style";
        $notitle = true;
    }

    if ($plotConfig['plot.fit.linear']) {
        $title = $notitle ? "notitle" : "title $titleRef";
        $style = $fstyle('plot.fit.linear.style', $i, $groupName, $allGroupsName);

        echo "f$i(x) = a$i + b$i*x\n";
        echo "fit f$i(x) '$groupName.dat' u $xPointPos:(tm(\${$yPointPos})) via a$i,b$i\n";

        $interpolate[] = "f$i(x) $title $style";
    }
    $i ++;
}
$tmp = \array_merge($interpolate, $lines, $points);

echo "plot ", implode(",\\\n", $tmp), "\n";
