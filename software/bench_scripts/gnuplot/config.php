<?php
return [
    'plotter.factory' => [
        'full' => fn (Plot $plot) => new Plotter\FullPlotter($plot, new Plotter\FullStrategy()),
        'full-line' => fn (Plot $plot) => (new Plotter\FullPlotter($plot, new Plotter\FullLineStrategy()))->setTemplate('full-line.plot.php'),
        'full-aggregation' => fn (Plot $plot) => new Plotter\FullPlotter($plot, new Plotter\FullAggregationStrategy()),
        'full-part' => fn (Plot $plot) => new Plotter\FullPartitioningPlotter($plot),
        'full-ppart' => fn (Plot $plot) => new Plotter\FullParallelPartitioningPlotter($plot),
        'noempty' => fn (Plot $plot) => new Plotter\FullNoEmptyPlotter($plot)
    ]
];
