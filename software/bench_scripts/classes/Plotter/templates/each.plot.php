<?php
$graphics = new Plotter\Graphics();

$exclude = [
    'datetime',
    'output.dir'
];
$blocGroups = [
    'bench',
    [
        'answers',
        'queries'
    ]
];
$data = $PLOTTER->getData();
$blocs = $graphics->prepareBlocs($blocGroups, $exclude, $data);


list ($yMin, $yMax) = $graphics->getYMinMax($PLOT->getData());
$ymin = \max(0, $yMin - 1);

$nbMeasures = \count(\array_filter($data, fn ($d) => \PLOT::isTimeMeasure($d)));
$nbBars = $nbMeasures * 2;


$graphics->compute($nbBars, $nbMeasures, $yMax);
echo $graphics->addFooter($blocs);

$w = $graphics['w'];
$h = $graphics['h'];

$lines = $graphics->plotYLines($yMax);
$fileName = $PLOTTER->getFileName('.png');
?>
set title "<?=$PLOT->gnuplotSpecialChars($fileName)?>"
set ylabel "time (ms)"
set key title "Times" bottom

set logscale y

set style data histograms
set style histogram gap 1

set xtics rotate by 30 right

set key autotitle columnheader
set key outside above	

set style fill pattern border -1

set style line 1 lc rgb 'black' lt 1 lw 1.5


set rmargin 0
set lmargin 0
set bmargin 0
set tmargin <?=(int)ceil($graphics['plot.header.h'] / $graphics['font.size'])?>

set label "<?=$data['bench']['datetime']?>" at screen 0.5,0 offset 0, character 1 center

set origin <?=$graphics['plot.x'] / $w?>, <?=$graphics['plot.y'] / $h?>

set size <?=$graphics['plot.w'] / $w?>, <?=$graphics['plot.h'] / $h?>

set term png size <?=$w?>, <?=$h?>

plot <?=$lines?> \
, '<?=$PLOTTER->getFileName('.dat')?>' u 2:xtic(1) ls 1 fs pattern 0, '' u 3 fs pattern 3 ls 1

