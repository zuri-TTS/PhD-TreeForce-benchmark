<?php
$graphics = new Plotter\Graphics();
$nbPlots = \count($PLOTTER->getCutData());
$nbMeasuresToPlot = \count($PLOTTER->getMeasuresToPlot());

$yMax = 0;
$yMin = PHP_INT_MAX;
$nbMeasures = 0;

$dirname = \basename(\dirname(\getcwd()));
$nbQueries = \count($PLOTTER->getQueries());

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

$nbBars = $nbMeasures * $nbMeasuresToPlot + 1;
$graphics->compute($nbBars, $nbMeasures, $yMax);
$yMin = \max(1, $yMin - 1);

$nbXPlots = $graphics['plots.max.x'] + 1;
$nbYPlots = \ceil((float) $nbPlots / $nbXPlots);
$plotXSize = 1.0 / ($nbXPlots);

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

$boxwidth = 0.25;
?>
set logscale y

set xtics rotate by 30 right

set key autotitle columnheader
set key inside left top title "Times"

set style fill pattern border -1
set boxwidth <?=$boxwidth?> absolute

set style line 1 lc rgb 'black' lt 1 lw 1.5

set term png size <?=$w?>, <?=$h?>

set multiplot layout <?=$multiColLayout?> title "<?=$theTitle?>"

set rmargin 0
set lmargin 0
set bmargin 10

<?php
$plot_lines = $graphics->plotYLines($yMax);
$ls = 1;
$nbPlots = 0;

foreach ($PLOTTER->getCutData() as $fname => $csvPaths) {
    $csvData = $PLOTTER->getCsvData($csvPaths[0]);
    $nbAnswers = $csvData['answers']['total'];
    $title = $PLOT->gnuplotSpecialChars($fname);
    $xmax = $nbMeasures + .25;
    $xmin = - .5;

    if (($nbPlots % $nbXPlots) === 0) {
        $ls = 1;
        echo "set title \"Placeholder\\n\"\n";
        echo "plot $yMin\n";
    } else {
        $ls ++;
    }

    echo "set title \"$title\\n($nbAnswers answers)\"\n";
    echo "set yrange [$yrange]\n";
    echo "set xrange [$xmin:$xmax]\n";
    echo "set ylabel offset 13,0 \"time (ms)\\n[$yrange]\"\n";

    $nb = 0;
    $pattern = 0;
    $tmp = [];

    $stacked = [
        [
            3 => 'rewriting.total.r',
        ],
        [
            4 => 'rewritings.generation'
        ],
        [
            5 => 'stats.db.time.r'
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

    echo "plot $plot_lines\\\n,", implode(',', $tmp), "\n";
}

    
