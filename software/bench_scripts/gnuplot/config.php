<?php
return [
    'plotter.factory' => [
        'each' => fn (Plot $plot) => new Plotter\EachPlotter($plot),
        'group' => fn (Plot $plot) => new Plotter\GroupPlotter($plot),
        'full' => fn (Plot $plot) => new Plotter\FullPlotter($plot, new Plotter\FullStrategy()),
        'full-line' => fn (Plot $plot) => (new Plotter\FullPlotter($plot, new Plotter\FullLineStrategy()))->setTemplate('full-line.plot.php'),
        'full-aggregation' => fn (Plot $plot) => new Plotter\FullPlotter($plot, new Plotter\FullAggregationStrategy()),
        'full-colls' => fn (Plot $plot) => new Plotter\FullCollsPlotter($plot),
        'noempty' => fn (Plot $plot) => new Plotter\FullNoEmptyPlotter($plot)
    ]
];
