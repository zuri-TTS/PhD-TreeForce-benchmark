<?php
$graphics = new Plotter\Graphics();
$nbPlots = \count($PLOTTER->getCutData());
$nbMeasuresToPlot = \count($PLOTTER->getMeasuresToPlot());

$yMax = 0;
$yMin = PHP_INT_MAX;
$nbMeasures = 0;

$dirname = \basename(\dirname(\getcwd()));
$nbQueries = Plotter\GroupPlotter::extractFirstNb($dirname);

if ($nbQueries > 0)
    $graphics['plots.max.x'] = $nbQueries;

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

$legendXMore = 300;
$graphics['w'] += $legendXMore;

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

$boxwidth = 0.25;
?>
set logscale y

set xtics rotate by 30 right

set key autotitle columnheader
set key lmargin top title "Times"

set style fill pattern border -1
set boxwidth <?=$boxwidth?>

set style line 1 lc rgb 'black' lt 1 lw 1.5

set term png size <?=$w?>, <?=$h?>

set multiplot layout <?=$multiColLayout?> title "<?=$theTitle?>"

<?php
$plot_lines = $graphics->plotYLines($yMax);
$ls = 1;
$nbPlots = 0;

foreach ($PLOTTER->getCutData() as $fname => $csvPaths) {
    $title = $PLOT->gnuplotSpecialChars($fname);
    echo "set title \"$title\"\n";
    echo "set yrange [$yrange]\n";
    echo "set ylabel \"time (ms)\\n[$yrange]\"\n";

    $nb = 0;
    $pattern = 0;
    $tmp = [];

    $stacked = [
        [
            3 => 'rewriting.total.r',
            2 => 'rewriting.rules.apply.r'
        ],
        [
            4 => 'stats.db.time.r'
        ]
    ];
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

    if (($nbPlots % $nbXPlots) === 0)
        $ls = 1;
    else
        $ls ++;

    echo "plot $plot_lines\\\n,", implode(',', $tmp), "\n";
}

    
