<?php
$timeDiv = 1000;

$plotterStrategy = $PLOTTER->getStrategy();

$plotConfig = $PLOTTER->plot_getConfig();
$csvGroups = $PLOTTER->getCsvGroups();
$csvFiles = \array_merge(...\array_values($csvGroups));
$nbPlots = $PLOTTER->getNbGroups();
$stacked = $plotterStrategy->plot_getStackedMeasures($plotConfig['plot.measures'] ?? []);
$nbMeasuresToPlot = \count($stacked);

$graphics = new Plotter\Graphics($plotConfig);
$plotLegend = $plotConfig['plot.legend'];
$plotYLabelYOffset = ($plotConfig['plot.ylabel.yoffset'] ?? null);
$plotYLabelYOffsetSub = ($plotConfig['plot.ylabel.yoffset.sub'] ?? $plotYLabelYOffset);
$plotYLabelXOffset = ($plotConfig['plot.ylabel.xoffset'] ?? null);
$plotYLabel = false;

if (isset($plotYLabelXOffset) || isset($plotYLabelYOffset)) {
    $plotYLabel = true;
    $plotYLabelXOffset = $plotYLabelXOffset ?? 0;
}

if ($plotYLabelYOffset < 0) {
    $plotYLabelYOffset = $graphics['font.size'] * ($plotConfig['plot.yrange.step'] / $graphics['plot.y.step']);
}
$plotYLabelYOffsetSub = $plotYLabelYOffset;

$ystep = $plotConfig['plot.yrange.step'];
$logscale = $plotConfig['logscale'];

list ($yMin, $yMax) = $plotterStrategy->plot_getYRange(...$csvFiles);

$nbQueries = \count($PLOTTER->getQueries());

if ($nbQueries > 0 && ! ($graphics['plots.max.x'] > 0))
    $graphics['plots.max.x'] = $nbQueries;

$getMeasure = function ($csvData, $what, $time) {
    return (int) (($csvData[$what] ?? [])[$time] ?? 0);
};
$nbMeasures = 0;

foreach ($PLOTTER->getCsvGroups() as $fname => $csvPaths) {
    $nbMeasures = \max($nbMeasures, \count($csvPaths));
}

$nbBars = $nbMeasures * $nbMeasuresToPlot;
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
$yRangeMax = $yLog;

while ($yRangeMax < $yMax)
    $yRangeMax += $yLog;

$yMax = $yRangeMax;
unset($yRangeMax);

$configYRangeMin = $plotConfig['plot.yrange.min'] ?? 0;

if ($configYRangeMin)
    $yMin = $configYRangeMin;
else
    $yMin /= $timeDiv;

if ($yMin < 1)
    $yMin = 0;

$configYRangeMax = $plotConfig['plot.yrange.max'] ?? 0;

if ($configYRangeMax)
    $yMax = $configYRangeMax;
else
    $yMax /= $timeDiv;

$yrange = "$yMin:$yMax";

$boxwidth = 1;

$key = $plotLegend ? <<<EOD
set key off
set key inside center center title "Times"

EOD : null;

$placeholderPlot = <<<EOD
unset title
unset key
unset xtics
unset ytics
set border 0
unset grid
unset ylabel
set key autotitle columnheader
$key
set format y "%gs"

EOD;

$normalPlot = <<<EOD
set xtics rotate by 30 right
set xtics scale 0
set ytics scale .2 nomirror $ystep
set border 1
set grid ytics
set key off

EOD;

if ($logscale) {
    echo "set logscale y\n";
    $plotYLabelYOffsetPattern = "($plotYLabelYOffset * 10 ** (log10(%s)-1))";
    $plotYLabelYOffsetSubPattern = "($plotYLabelYOffsetSub * 10 ** (log10($yMax)-1))";
}

if ($plotConfig['multiplot.title'] === true) {
    $theTitle = \dirname(\dirname(\array_keys($PLOT->getData())[0]));
    $multiplotTitle = "title \"$theTitle\"\n";
} else
    $multiplotTitle = null;

$xmax = $boxwidth * $nbBars - $boxwidth / 2;
$xmin = - $boxwidth / 2;

$xmin -= $boxwidth * $graphics['bar.offset.factor'];
$xmax += $boxwidth * ($graphics['bar.end.factor'] + $graphics['bar.gap.nb']);

echo <<<EOD
if(!exists("terminal")) terminal="png"

tm(x)=x/$timeDiv

set style fill pattern border -1
set boxwidth $boxwidth
set style line 1 lc rgb 'black' lt 1 lw .5
set term terminal size $w, $h
set multiplot layout $multiColLayout $multiplotTitle
set rmargin 0
set lmargin 0
set bmargin 10
set yrange [$yrange]
set xrange [$xmin:$xmax]

EOD;

$ls = 1;
$nbPlots = 0;
$gap = $graphics['bar.gap.factor'];

foreach ($PLOTTER->getCsvGroups() as $fname => $csvPaths) {
    $csvData = $PLOTTER->getCsvData($csvPaths[0]);
    $nbAnswers = $csvData['answers']['total'];

    if (null !== ($f = $plotConfig['plot.title']))
        $title = $f($fname, $nbAnswers);
    else
        $title = "$fname\\n($nbAnswers answers)";

    $title = $PLOT->gnuplotSpecialChars($title);

    if (($nbPlots % $nbXPlots) === 0) {
        $tmp = [];
        $ls = 1;
        $pattern = (int) ($plotConfig['plot.pattern.offset'] ?? 0);

        foreach ($stacked as $stack) {

            foreach ($stack as $pos => $measure) {
                $legendTitle = $plotLegend ? "title '$measure'" : "";
                $tmp[] = "1/0 with boxes $legendTitle ls $ls fs pattern $pattern";
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

    if ($plotConfig['plot.yrange'] === 'local') {
        list ($min, $max) = $plotterStrategy->plot_getYRange(...$csvPaths);
        $yMin /= $timeDiv;
        $yMax /= $timeDiv;

        if (! $logscale) {
            $minZone = $maxZone = 0;

            for ($i = 0; $i < $yMin; $i += $ystep, $minZone ++);
            for ($i = 0; $i < $yMax; $i += $ystep, $maxZone ++);

            $yMin = ($minZone - 1) * $ystep;
            $yMax = $maxZone * $ystep;
        }
        $yrange = "$yMin:$yMax";
        echo "set yrange [$yrange]\n";
    }

    if ($plotConfig['plot.yrange.display'] ?? false)
        echo "set ylabel offset 15,0 \"[$yrange]\"\n";

    echo "set title \"$title\"\n";

    $pattern = (int) ($plotConfig['plot.pattern.offset'] ?? 0);
    $tmp = [];
    $xtics = ':xtic(1)';
    $spaceFactor = $boxwidth * $nbMeasuresToPlot + $gap;
    $stacked_i = 0;

    foreach ($stacked as $stack) {

        foreach ($stack as $pos => $measure) {
            $measure = $PLOT->gnuplotSpecialChars($measure);
            $tmp[] = "'$fname.dat' u (\$0 * $spaceFactor + $stacked_i):(tm(\$$pos))$xtics with boxes title '$measure' ls $ls fs pattern $pattern";

            if ($plotYLabel) {

                if ($logscale) {
                    $plotYLabelYOffset = sprintf($plotYLabelYOffsetPattern, "tm(\$$pos)");
                    $plotYLabelYOffsetSub = sprintf($plotYLabelYOffsetSubPattern, "tm(\$$pos)");
                }
                $tmp[] = "'' u " . //
                "(\$0 * $spaceFactor + $stacked_i + (tm(\$$pos) >= $yMax ? $plotYLabelXOffset : 0)):" . //
                "(tm(\$$pos) + $plotYLabelYOffset >= $yMax) ? $yMax - $plotYLabelYOffsetSub : ( (tm(\$$pos) > $yMin ? tm(\$$pos) : $yMin) + $plotYLabelYOffset):" . //
                "(sprintf(\"%.2f\", tm(\$$pos)))" . //
                " with labels font \",8\"";
            }
            $xtics = null;
            $pattern ++;
        }
        $stacked_i += $boxwidth;
    }
    $nbPlots ++;

    echo "plot\\\n", implode(",\\\n", $tmp), "\n";
}
