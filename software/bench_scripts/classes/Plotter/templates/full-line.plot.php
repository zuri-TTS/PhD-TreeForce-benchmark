set terminal png size 1000,500
set key outside left

set xrange [0:800]

<?php
$plotConfig = $PLOTTER->getStrategy()->getPlotConfig();

echo <<<EOD
set xlabel "{$plotConfig['xlabel']}"
set ylabel "{$plotConfig['ylabel']}"
set format y "%gs"

EOD;

$ls = 0;
$tmp = [];

foreach ($PLOTTER->getCsvGroups() as $group => $csvPaths) {
    $csvData = $PLOTTER->getCsvData($csvPaths[0]);
    $title = $PLOT->gnuplotSpecialChars($group);

    $ls ++;

    $tmp[] = "'$group.dat' u 2:($3/1000) with points title \"$group\" ls $ls\\\n";
}
echo "plot ", implode(',', $tmp), "\n";
