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

<?php include __DIR__. '/print_bench_config.php'?>

<?=Plot::gnuplot_setTerminal($val)?>

<?=$val['plot']($val)?>
