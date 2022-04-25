<?php
$graphics = new Plotter\Graphics();
$plotterStrategy = $PLOTTER->getStrategy();

$nbPlots = $PLOTTER->getNbGroups();
$stacked = $plotterStrategy->plot_getStackedMeasures();
$nbMeasuresToPlot = \count($stacked);

list ($yMin, $yMax) = $plotterStrategy->plot_getYRange();

$plot_wMin = 600;

$nbQueries = \count($PLOTTER->getQueries());

if ($nbQueries > 0)
    $graphics['plots.max.x'] = $nbQueries;

$getMeasure = function ($csvData, $what, $time) {
    return (int) (($csvData[$what] ?? [])[$time] ?? 0);
};
$nbMeasures = 0;

foreach ($PLOTTER->getCsvGroups() as $fname => $csvPaths) {
    $nbMeasures = \max($nbMeasures, \count($csvPaths));
}

$nbBars = $nbMeasures * $nbMeasuresToPlot + 1;
$graphics->compute($nbBars, $nbMeasures, $yMax);
$yMin = \max(1, $yMin - 1);

$nbXPlots = $graphics['plots.max.x'] + 1;
$nbYPlots = \ceil((float) $nbPlots / $nbXPlots);
$plotXSize = 1.0 / ($nbXPlots);

$multiColLayout = "$nbYPlots, $nbXPlots";
$plotSize = (1.0 / $nbXPlots) . "," . (1.0 / $nbYPlots);

$graphics['w'] = \max($graphics['w'], $plot_wMin);
$graphics['w'] *= $nbXPlots;
$graphics['h'] *= $nbYPlots;
$h = $graphics['h'];
$w = $graphics['w'];

$yLog = 10 ** floor(\log10($yMax));
$yRangeMax = $yLog;

while ($yRangeMax < $yMax)
    $yRangeMax += $yLog;

$yrange = "$yMin:$yRangeMax";

$theTitle = \dirname(\dirname(\array_keys($PLOT->getData())[0]));

$boxwidth = 0.25;

$placeholderPlot = <<<EOD
unset title
unset key
unset xtics
unset ytics
set border 0
unset grid
set key off
unset ylabel
set key inside center center title "Times"
set key autotitle columnheader
set format y "%gms"

EOD;

$normalPlot = <<<EOD
set xtics rotate by 30 right
set xtics scale 0
set ytics scale .2 nomirror
set border 1
set grid ytics
set key off
set ylabel offset 15,0 "[$yrange]"

EOD;

echo <<<EOD
set logscale y
set style fill pattern border -1
set boxwidth $boxwidth
set style line 1 lc rgb 'black' lt 1 lw .5
set term png size $w, $h
set multiplot layout $multiColLayout title "$theTitle"
set rmargin 0
set lmargin 0
set bmargin 10

EOD;

$xmax = $nbMeasures + $boxwidth;
$xmin = - $boxwidth * 2;
echo "set yrange [$yrange]\n";
echo "set xrange [$xmin:$xmax]\n";

$ls = 1;
$nbPlots = 0;

foreach ($PLOTTER->getCsvGroups() as $fname => $csvPaths) {
    $csvData = $PLOTTER->getCsvData($csvPaths[0]);
    $nbAnswers = $csvData['answers']['total'];
    $title = $PLOT->gnuplotSpecialChars($fname);

    if (($nbPlots % $nbXPlots) === 0) {
        $tmp = [];
        $ls = 1;
        $pattern = 0;

        foreach ($stacked as $stack) {

            foreach ($stack as $pos => $measure) {
                $tmp[] = "1/0 with boxes title '$measure' ls $ls fs pattern $pattern";
                $pattern ++;
            }
        }
        echo $placeholderPlot;
        echo 'plot ', \implode(',', $tmp), "\n";
        echo $normalPlot;
        $nbPlots ++;
    } else {
        $ls ++;
    }

    echo "set title \"$title\\n($nbAnswers answers)\"\n";

    $nb = 0;
    $pattern = 0;
    $tmp = [];
    $xtics = ':xtic(1)';

    foreach ($stacked as $stack) {
        $offset = $nb * $boxwidth;

        foreach ($stack as $pos => $measure) {
            $measure = $PLOT->gnuplotSpecialChars($measure);
            $tmp[] = "'$fname.dat' u ($0 + $offset):$pos$xtics with boxes title '$measure' ls $ls fs pattern $pattern \\\n";
            $xtics = null;
            $pattern ++;
        }
        $nb ++;
    }
    $nbPlots ++;

    echo "plot", implode(',', $tmp), "\n";
}
