<?php
return [
    'plotter.factory' => [
        'each' => fn (Plot $plot) => new Plotter\EachPlotter($plot),
        'group' => fn (Plot $plot) => new Plotter\GroupPlotter($plot),
        'full' => fn (Plot $plot) => new Plotter\FullPlotter($plot),
        'full-colls' => fn (Plot $plot) => new Plotter\FullCollsPlotter($plot)
    ]
];
