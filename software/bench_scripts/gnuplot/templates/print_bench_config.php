set label "<?=$val['bench']['datetime']?>" at screen 0.5,0 offset 0, character 1 center
<?php
$exclude = [
    'datetime',
    'output.dir',
    'query_native'
];
$s = '';
$configNbLine = 0;

foreach ($val['bench'] as $k => $v) {

    if (in_array($k, $exclude))
        continue;

    $s .= "$k: $v\\n";
    $configNbLine ++;
}

$spaceHeight = 50;
$spaceWidth = 250;

$queryWidth = 50 * ($val['queries.nb'] ?? 1) + 10;

$yNbLine = (int) ceil(log10($val['time.real.max']));

$plotHeight = $yNbLine * 150 + 100;

$height = 15 * $configNbLine + $spaceHeight + $plotHeight;
$width = $val['time.nb'] * $queryWidth + $spaceWidth;

$val['terminal.size'] = "$width,$height";

$plotSizeH = $plotHeight / $height;
$plotOriginY = 1 - $plotSizeH;
?>

set label "<?=$s?>" at screen 0.01,0.01 offset character 0, character <?=$configNbLine+1?>

set origin 0, <?= $plotOriginY?>

set size 1, <?=$plotSizeH?>
