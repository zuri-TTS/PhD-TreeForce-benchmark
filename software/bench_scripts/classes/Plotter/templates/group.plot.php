<?php
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
$data = \array_values($PLOT->getData())[0];
$blocs[] = $PLOT->prepareOneBloc($blocGroup, $exclude, $data);

$blocGroup = [
    'answers',
    'queries',
    'bench'
];

foreach ($PLOTTER->getCSVPaths() as $path) {
    $data = $PLOT->getData()[$path];
    $fileName = Plot::plotterFileName($PLOTTER, $path);
    $bloc = &$blocs[];
    $bloc[] = [
        "<$fileName>"
    ];
    $bloc += $PLOT->prepareOneBloc($blocGroup, [], [
        'bench' => [
            'measures.nb' => $data['bench']['measures.nb'],
            'measures.forget' => $data['bench']['measures.forget']
        ]
    ] + $data);
}

echo $PLOT->addFooter($blocs);

$w = $graphics['w'];
$h = $graphics['h'];

?>
set title "<?=$PLOTTER->getGroupPath()?>"
set ylabel "time (ms)"
set key title "Times"

set label "<?=$data['datetime'] ?? ''?>" at screen 0.99, 0.5 center rotate by 90

set logscale y


set style data histograms
set style histogram gap 1


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

<?php
$plot_lines = $PLOT->getPlotYLines();
$val = $PLOT->getPlotVariables();
$ls = 1;

foreach ($PLOTTER->getCSVPaths() as $f) {
    $fname = \basename($f, '.csv') .'_time';
    $tmp[] = <<<EOD
    '$fname.dat' u 2:xtic(1) title '$fname real' ls $ls fs pattern 0 \\
    ,'' u 3 title '$fname cpu' fs pattern 3 ls $ls \\\n
    EOD;
    $ls ++;
}
echo "plot $plot_lines\\\n,", implode(',', $tmp), "\n";

