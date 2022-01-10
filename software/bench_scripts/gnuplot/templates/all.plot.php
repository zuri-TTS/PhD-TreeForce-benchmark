<?php
$val = $PLOT->getPlotVariables();
$graphics = &$PLOT->getPlotGraphics();

$exclude = [
    'datetime',
    'output.dir',
    'measures.nb',
    'measures.forget'
];
$blocGroup = [
    'bench'
];
$blocs[] = $PLOT->prepareOneBloc($blocGroup, $exclude);

$blocGroup = [
    'answers',
    'queries',
    'bench'
];

foreach ($val['files'] as $fileVal) {
    $bloc = &$blocs[];
    $bloc[] = ["<{$fileVal['file.name']}>"];
    $bloc += $PLOT->prepareOneBloc($blocGroup, [], [
        'bench' => [
            'measures.nb' => $fileVal['bench']['measures.nb'],
            'measures.forget' => $fileVal['bench']['measures.forget']
        ]
    ] + $fileVal);
}

echo $PLOT->addFooter($blocs);

$w = $graphics['w'];
$h = $graphics['h'];
?>
set title "<?=$val['out.base.path'] ?? ''?>"
set ylabel "time (ms)"
set key title "Times"

set label "<?=$val['datetime'] ?? ''?>" at screen 0.99, 0.5 center rotate by 90

set logscale y


set style data histograms
set style histogram gap <?=$val['plot.histogram.gap'] ?? 1?>


set xtics rotate by 30 right

set key autotitle columnheader
set key outside above	

set style line 1 lc rgb 'black' lt 1 lw 1.5

set rmargin 0
set lmargin 0
set bmargin 0
set tmargin <?=(int)ceil($graphics['plot.header.h'] / $graphics['font.size'])?>


set origin <?=$graphics['plot.x'] / $w?>, <?=$graphics['plot.y'] / $h?>

set size <?=$graphics['plot.w'] / $w?>, <?=$graphics['plot.h'] / $h?>

set term png size <?=$w?>, <?=$h?>

<?=$val['plot']($PLOT)?>
