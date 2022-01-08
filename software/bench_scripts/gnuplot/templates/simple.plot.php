<?php
$val = $PLOT->getPlotVariables();
$graphics = &$PLOT->getPlotGraphics();

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
$blocs = $PLOT->prepareBlocs($blocGroups, $exclude);
echo $PLOT->addFooter($blocs);

$w = $graphics['w'];
$h = $graphics['h'];
?>
set title "<?=$val['file.name']?>"
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

set label "<?=$val['bench']['datetime']?>" at screen 0.5,0 offset 0, character 1 center

set origin <?=$graphics['plot.x'] / $w?>, <?=$graphics['plot.y'] / $h?>

set size <?=$graphics['plot.w'] / $w?>, <?=$graphics['plot.h'] / $h?>

set term png size <?=$w?>, <?=$h?>

<?=$val['plot']($PLOT)?>
