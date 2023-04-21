<?php
$graphics = new Plotter\Graphics();

$nbQueries = \count($PLOT->getData());

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
$blocs[] = $graphics->prepareOneBloc($blocGroup, $exclude, $data);

$blocGroup = [
    'answers',
    'queries',
    'bench'
];

foreach ($PLOTTER->getData() as $qname => $data) {
    $bloc = &$blocs[];
    $bloc[] = [
        "<$qname>"
    ];
    $bloc += $graphics->prepareOneBloc($blocGroup, [], [
        'bench' => [
            'measures.nb' => $data['bench']['measures.nb'],
            'measures.forget' => $data['bench']['measures.forget']
        ]
    ] + $data);
}

list ($yMin, $yMax) = $graphics->getYMinMax($PLOT->getData());
$ymin = \max(0, $yMin - 1);

$nbMeasures = \count(\array_filter($data, fn ($d) => \Measures::isTimeMeasure($d)));
$nbBars = $nbMeasures * $nbQueries * 2;

$graphics->compute($nbBars, $nbMeasures, $yMax);

echo $graphics->addFooter($blocs);
$w = $graphics['w'];
$h = $graphics['h'];
?>
set title "<?=$PLOT->gnuplotSpecialChars(\basename($PLOTTER->getDirName()))?>"
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

# set size <?=$graphics['plot.w'] / $w?>, <?=$graphics['plot.h'] / $h?>

set term png size <?=$h?>, <?=$w?>

<?php
$plot_lines = $graphics->plotYLines($yMax);
$ls = 1;

foreach ($PLOTTER->getData() as $qname => $data) {
    $title = $PLOT->gnuplotSpecialChars("Q$qname");

    $tmp[] = <<<EOD
    '{$qname}_time.dat' u 2:xtic(1) title "$title real" ls $ls fs pattern 0 \\
    ,'' u 3 title "$title cpu" fs pattern 3 ls $ls \\\n
    EOD;
    $ls ++;
}
echo "plot $plot_lines\\\n,", implode(',', $tmp), "\n";

