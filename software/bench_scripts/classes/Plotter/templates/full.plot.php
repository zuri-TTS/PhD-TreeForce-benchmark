<?php
$graphics = new Plotter\Graphics();
$nbPlots = \count($PLOTTER->getCutData());
$nbMeasuresToPlot = \count($PLOTTER->getMeasuresToPlot());

$yMax = 0;
$yMin = PHP_INT_MAX;
$nbMeasures = 0;

foreach ($PLOTTER->getCsvData() as $csvData) {

    foreach ($PLOTTER->toPlot() as $what => $measure) {
        $v = (int) $csvData[$what][$measure];
        $yMax = \max($yMax, $v);
        $yMin = \min($yMin, $v);
    }
}
foreach ($PLOTTER->getCutData() as $fname => $csvPaths) {
    $nbMeasures = \max($nbMeasures, \count($csvPaths));
}

$nbBars = $nbMeasures * $nbMeasuresToPlot;
$graphics->compute($nbBars, $nbMeasures, $yMax);
$yMin = \max(1, $yMin - 1);

$nbXPlots = $graphics['plots.max.x'];
$nbYPlots = \ceil((float) $nbPlots / $nbXPlots);

$multiColLayout = "$nbYPlots, $nbXPlots";
$plotSize = (1.0 / $nbXPlots) . "," . (1.0 / $nbYPlots);

$graphics['w'] *= $nbXPlots;
$graphics['h'] *= $nbYPlots;
$h = $graphics['h'];
$w = $graphics['w'];

$yLog = 10 ** floor(\log10($yMax));
$yRange = $yLog;

while ($yRange < $yMax)
    $yRange += $yLog;

$yrange = "$yMin:$yRange";

$theTitle = \dirname(\dirname(\array_keys($PLOT->getData())[0]));
?>
set key title "Times" bottom

set logscale y

set style data histograms
set style histogram gap 1

set xtics rotate by 30 right

set key autotitle columnheader
set key outside above	

set style fill pattern border -1

set style line 1 lc rgb 'black' lt 1 lw 1.5

set term png size <?=$w?>, <?=$h?>

set multiplot layout <?=$multiColLayout?> title "<?=$theTitle?>"

<?php
$plot_lines = $graphics->plotYLines($yMax);
$ls = 1;

foreach ($PLOTTER->getCutData() as $fname => $csvPaths) {
    $title = $PLOT->gnuplotSpecialChars($fname);
    echo "set title \"$title\"\n";
    echo "set yrange [$yrange]\n";
    echo "set ylabel \"time (ms)\\n[$yrange]\"\n";

    $i = 2;
    $pattern = 0;
    $tmp = [];

    foreach ($PLOTTER->getMeasuresToPlot() as $measure) {
        $measure = $PLOT->gnuplotSpecialChars($measure);
        $tmp[] = "'$fname.dat' u $i:xtic(1) title '$measure' ls $ls fs pattern $pattern \\\n";
        $i ++;
        $pattern ++;
    }
    $ls ++;
    echo "plot $plot_lines\\\n,", implode(',', $tmp), "\n";
}

    
