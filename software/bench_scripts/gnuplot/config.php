<?php
return [
    'plotter.factory' => [
        fn (Plot $plot) => new Plotter\EachPlotter($plot),
        fn (Plot $plot) => new Plotter\GroupPlotter($plot)
    ]
];
