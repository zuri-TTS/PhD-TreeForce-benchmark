<?php
return  [
	'plots' => [
		'default' => [
			'terminal.type' => 'png',
// 			'terminal.size' => '1200,300',

		    'measure.exclude' => [
		    ],
			'plot.output.path' => function(array $PLOT): string
			{
				return realpath($PLOT['out.dir.path'])."/${PLOT['out.file.name']}.png";
			}
		],
		include __DIR__ . '/config-time.php',
		include __DIR__ . '/config-time-all.php',
	]
];
