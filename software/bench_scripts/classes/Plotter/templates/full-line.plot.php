set terminal pngcairo size 1000,500
set key outside below horizontal maxcols 2
<?php
$plotConfig = $PLOTTER->plot_getConfig();

// Define what to plot
{
    $xPointPos = 2;
    $yPointPos = 3;
}

$xRangeMax = $plotConfig['plot.xrange.max'] ?? null;
$yRangeMax = $plotConfig['plot.yrange.max'] ?? null;

if ($yRangeMax !== null)
    echo "set yrange [0:$yRangeMax]\n";

echo <<<EOD
set xrange [0:$xRangeMax]
set xlabel "{$plotConfig['xlabel']}"
set ylabel "{$plotConfig['ylabel']}"
set format y "%gs"

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

foreach ($PLOTTER->getCsvGroups() as $group => $csvPaths) {
    $csvData = $PLOTTER->getCsvData(\Help\Arrays::first($csvPaths));
    $dirName = \basename(\dirname(\Help\Arrays::first($csvPaths)));
    $delements = \Help\Plotter::extractDirNameElements($dirName);
    $sdelements = \Help\Arrays::subSelect($delements, $selection);

    $titleRef = \Plotter\AbstractFullStrategy::makeXTic_fromDirName(\Help\Plotter::encodeDirNameElements($sdelements, ''));

    if ($hasMultiplePartitioning) {
        $titleRef = "{$delements['partitioning']} $titleRef";
    }
    if ($hasMultipleDataset) {
        $delim = $hasMultiplePartitioning ? '.' : ' ';
        $titleRef = "{$delements['group']}$delim$titleRef";
    }

    $titleRef = "\"$titleRef\"";

    $notitle = false;
    $styleReplacement = [
        'lc' => $i,
        'lt' => $i
    ];

    if ($plotConfig['plot.points']) {
        $title = $notitle ? "notitle" : "title $titleRef";
        $style = $plotConfig['plot.points.style'];
        $style = \str_format($style, $styleReplacement);

        $points[] = "'$group.dat' u $xPointPos:(\${$yPointPos}/1000) with points $title $style";
        $notitle = true;
    }

    if ($plotConfig['plot.lines']) {
        $title = $notitle ? "notitle" : "title $titleRef";
        $style = $plotConfig['plot.lines.style'];
        $style = \str_format($style, $styleReplacement);

        $lines[] = "'$group.dat' u $xPointPos:(\${$yPointPos}/1000) with lines $title $style";
        $notitle = true;
    }

    if ($plotConfig['plot.fit.linear']) {
        $title = $notitle ? "notitle" : "title $titleRef";
        $style = $plotConfig['plot.fit.linear.style'];
        $style = \str_format($style, $styleReplacement);

        echo "f$i(x) = a$i + b$i*x\n";
        echo "fit f$i(x) '$group.dat' u $xPointPos:(\${$yPointPos}/1000) via a$i,b$i\n";

        $interpolate[] = "f$i(x) $title $style";
    }
    $i ++;
}
$tmp = \array_merge($interpolate, $lines, $points);

echo "plot ", implode(",\\\n", $tmp), "\n";
