<?php
$plotterStrategy = $PLOTTER->getStrategy();

$plotConfig = $PLOTTER->plot_getConfig();
$csvGroups = $PLOTTER->getCsvGroups();
$csvFiles = \array_merge(...\array_values($csvGroups));
$nbPlots = $PLOTTER->getNbGroups();
$stacked = $plotterStrategy->plot_getStackedMeasures($plotConfig['plot.measures'] ?? []);
$nbMeasuresToPlot = \count($stacked);

$graphics = new Plotter\Graphics($plotConfig);
$plotLegend = $plotConfig['plot.legend'];
$plotYTicsStep = $plotConfig['plot.ytics.step'];
$nbYTics_max = (int) $plotConfig['plot.ytics.nb'];
$plotYLabelYOffset = ($graphics['plot.ylabel.yoffset'] ?? null);
$plotYLabelXOffset = ($graphics['plot.ylabel.xoffset'] ?? null);
$dataFormat = $plotConfig['plot.ylabel.format'] ?? "%.1f";
$plotYLabel = false;
$timeDiv = $plotConfig['measure.div'];
$formatY = $plotConfig['plot.format.y'];

if (isset($plotYLabelXOffset) || isset($plotYLabelYOffset)) {
    $plotYLabel = true;
    $plotYLabelXOffset = $plotYLabelXOffset ?? 0;
}

if ($plotYLabelYOffset < 0) {
    $plotYLabelYOffset = (float) $graphics['plot.yrange.step'] * $graphics['font.size'] / $graphics['plot.y.step'] / 2;
}

$ystep = $graphics['plot.yrange.step'];
$logscale = $graphics['logscale'];

list ($yMin, $yMax) = $plotterStrategy->plot_getYRange(...$csvFiles);
$yMin = \max(1, $yMin - 1);
$yMin = $graphics['plot.yrange.min'] ?? $yMin / $timeDiv;
$yMax = $graphics['plot.yrange.max'] ?? $yMax / $timeDiv;

$nbQueries = \count($PLOTTER->getQueries());

if ($nbQueries > 0 && ! ($graphics['plots.x.max'] > 0))
    $graphics['plots.x.max'] = $nbQueries;

$getMeasure = function ($csvData, $what, $time) {
    return (int) (($csvData[$what] ?? [])[$time] ?? 0);
};
$nbMeasures = 0;

foreach ($PLOTTER->getGroupsInfos() as $groupName => $infos)
    $nbMeasures = \max($nbMeasures, $infos['nb']);

$nbBars = $nbMeasures * $nbMeasuresToPlot;

{ // YRange
    $logscaleBase = $graphics->logscaleBase();
    $autoYMax = ! isset($graphics['plot.yrange.max']);

    if ($autoYMax) {

        if ($logscale) {

            $yLog = $logscaleBase ** floor(\log($yMax, $logscaleBase));
            $yRangeMax = $yLog;
            $step = $yLog;
        } else {
            $yRangeMax = $yMin;
            $step = $graphics['plot.yrange.substep'] ?? 10;
        }

        while ($yRangeMax < $yMax)
            $yRangeMax += $step;

        $yMax = $yRangeMax;
        unset($yRangeMax);
    }
    $yrange = "$yMin:$yMax";
    $graphics['plot.yrange.min'] = $yMin;
    $graphics['plot.yrange.max'] = $yMax;
}
$graphics->compute($nbBars, $nbMeasures, $nbPlots);

if ($logscale) {
    echo "set logscale y $logscaleBase\n";

    $maxScale = log($yMax, $logscaleBase);
    $minScale = log($yMin, $logscaleBase);
    $rangeScale = $maxScale - $minScale;

    $unit = $plotYLabelYOffset / $graphics['plot.h'] * log($yMax, $logscaleBase);
    $plotYLabelYOffsetPattern = "10 ** ($unit + log10(%s))";
    $plotYLabelYOffsetMaxPattern = "10 ** ($maxScale - $unit)";
    $plotYLabelYOffsetMinPattern = "10 ** ($minScale + $unit)";

    $yMaxTh = $yMinTh = 0;
} else {
    $unit = $graphics['plot.yrange.step'] / $graphics['plot.y.step'] * $plotYLabelYOffset;
    $plotYLabelYOffsetPattern = "%s + $unit";
    $plotYLabelYOffsetMaxPattern = "$yMax - $unit";
    $plotYLabelYOffsetMinPattern = "$yMin + $unit";

    $yMaxTh = $yMinTh = $unit;
}
$nbXPlots = $graphics['plots.x'];
$nbYPlots = $graphics['plots.y'];

$multiColLayout = "$nbYPlots, $nbXPlots";
$plotSize = (1.0 / $nbXPlots) . "," . (1.0 / $nbYPlots);

$h = $graphics['h'];
$w = $graphics['w'];

$boxwidth = 1;

$plotYTics = "set format y \"$formatY\"\n";

if ($plotConfig['multiplot.title'] === true) {
    $theTitle = \dirname(\dirname(\array_keys($PLOT->getData())[0]));
    $multiplotTitle = "title \"$theTitle\"\n";
} else
    $multiplotTitle = null;

$xmax = $boxwidth * $nbBars - $boxwidth / 2;
$xmin = - $boxwidth / 2;

$xmin -= $boxwidth * $graphics['bar.offset.factor'];
$xmax += $boxwidth * ($graphics['bar.end.factor'] + $graphics['bar.gap.nb']);

$ls = 1;
$nbPlots = 0;
$gap = $graphics['bar.gap.factor'];

echo <<<EOD
if(!exists("terminal")) terminal="png"

tm(x)=x/($timeDiv)
set style fill pattern border -1
set boxwidth $boxwidth
set style line 1 lc rgb 'black' lt 1 lw .5
set term terminal size $w, $h noenhance
set multiplot

set style textbox opaque noborder

set lmargin 0
set rmargin 0
set bmargin 0
set tmargin 0

EOD;

$tinyFont = "Noto Sans,8";

if ($plotLegend) {
    $tmp = [];
    $pattern = (int) ($plotConfig['plot.pattern.offset'] ?? 0);
    $wfactor = ($plotConfig['plot.legend.w'] ?? $graphics['layout.lmargin']) / $graphics['w'];

    foreach ($stacked as $stack) {

        foreach ($stack as $pos => $measure) {
            $legendTitle = "title '$measure'";
            $tmp[] = "1/0 with boxes $legendTitle ls $ls fs pattern $pattern";
            $pattern ++;
        }
    }
    list ($lin, $col) = $graphics->plotPositionFactors(0, false);
    $plot = \implode(',', $tmp);
    echo <<<EOD
    unset title
    unset xtics
    unset ytics
    unset ylabel
    set key inside right center vertical samplen .5
    set xrange [0:1]
    set yrange [0:1]
    set origin $col,$lin
    set border 0
    set size $wfactor,{$graphics['plot.h.factor']}
    plot $plot
    
    EOD;
}

echo <<<EOD
set size {$graphics['plot.w.factor']},{$graphics['plot.h.factor']}
set yrange [$yrange]
set xrange [$xmin:$xmax]

set xtics rotate by 30 right
set xtics scale 0
set border 1
set grid ytics
set ytics scale .2 nomirror $ystep
set key autotitle columnheader
set key off

EOD;

foreach ($PLOTTER->getCsvGroups() as $fname => $csvPaths) {
    $csvData = $PLOTTER->getCsvData($csvPaths[0]);
    $nbAnswers = $csvData['answers']['total'];

    if (null !== ($f = $plotConfig['plot.title']))
        $title = $f($fname, $nbAnswers);
    else
        $title = "$fname\\n($nbAnswers answers)";

    $title = $title;

    list ($lin, $col) = $graphics->plotPositionFactors($nbPlots);
    echo "set origin $col,$lin\n";

    if (($nbPlots % $nbXPlots) === 0) {
        $nbYTics = 0;
        // $tmp = [];
        $ls = 1;
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

    if ($nbYTics !== $nbYTics_max && $plotYTicsStep && ($nbPlots % $nbXPlots - 1) % $plotYTicsStep == 0) {
        echo $plotYTics;
        $nbYTics ++;
    } else {
        echo "set format y \"\"\n";
    }

    $pattern = (int) ($plotConfig['plot.pattern.offset'] ?? 0);
    $tmp = [];
    $xtics = ':xtic(1)';
    $spaceFactor = $boxwidth * $nbMeasuresToPlot + $gap;
    $stacked_i = 0;

    foreach ($stacked as $stack) {

        foreach ($stack as $pos => $measure) {
            $measure = $measure;
            $tmp[] = "'$fname.dat' u (\$0 * $spaceFactor + $stacked_i):(tm(\$$pos))$xtics with boxes title '$measure' ls $ls fs pattern $pattern";

            if ($plotYLabel) {
                $plotYLabelYOffset = sprintf($plotYLabelYOffsetPattern, "tm(\$$pos)");
                $plotYLabelYOffsetMax = sprintf($plotYLabelYOffsetMaxPattern, "tm(\$$pos)");
                $plotYLabelYOffsetMin = sprintf($plotYLabelYOffsetMinPattern, "tm(\$$pos)");

                $tmp[] = "'' u " . //
                "(\$0 * $spaceFactor + $stacked_i):" . //
                "($plotYLabelYOffset +  $yMaxTh >= $yMax) ? $plotYLabelYOffsetMax : (($plotYLabelYOffset - $yMinTh <= $yMin) ? $plotYLabelYOffsetMin : $plotYLabelYOffset):" . //
                "(sprintf(\"$dataFormat\", tm(\$$pos)))" . //
                " with labels boxed font \"$tinyFont\"";
            }
            $xtics = null;
            $pattern ++;
        }
        $stacked_i += $boxwidth;
    }
    $nbPlots ++;
    $ls ++;

    echo "plot\\\n", implode(",\\\n", $tmp), "\n";
}
