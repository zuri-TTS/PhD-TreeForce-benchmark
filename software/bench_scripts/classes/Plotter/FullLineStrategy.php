<?php
namespace Plotter;

final class FullLineStrategy extends AbstractFullStrategy
{

    private const toPlot_reformulations = [
        'partitions.infos' => 'all.queries.nb',
        'stats.db.time|threads.time' => 'r'
    ];

    private const toPlot_rules = [
        'rules' => 'queries.nb.intended',
        'stats.db.time|threads.time' => 'r'
    ];

    private const stackedMeasuresToPlot = [
        [
            2 => 'reformulations'
        ],
        [
            3 => 'time'
        ]
    ];

    private const plotConfig = [
        'xlabel' => 'reformulations',
        'ylabel' => 'answering time',
        "plot.points" => false,
        "plot.lines" => false,
        "plot.fit.linear" => true,
        "plot.points.style" => "lt %lt lc %lc ps 1.75 lw 2",
        "plot.lines.style" => "lc %lc",
        "plot.fit.linear.style" => "dt 5 lw 1 lc %lc"
    ];

    public function __construct()
    {
        parent::__construct();
        $this->setStackedMeasuresToPlot(self::stackedMeasuresToPlot);
    }

    public function getID(): string
    {
        return 'line';
    }

    private const SELECT_ELEMENTS = [
        'full_group',
        'summary'
    ];

    public function plot_getConfig(array $default = []): array
    {
        return parent::plot_getConfig($default) + self::plotConfig;
    }

    public function sortDataLines(array &$data): void
    {
        uasort($data, function ($a, $b) {
            $ret = $a[1] - $b[1];

            if ($ret === 0)
                return $a[2] - $b[2];

            return $ret;
        });
    }

    public function groupCSVFiles(array $csvFiles): array
    {
        $g = $this->getPlotter()->plot_getConfig()['@group'];

        switch ($g) {
            case '':
            case 'reformulations':
                $this->setToPlot(self::toPlot_reformulations);
                break;

            case 'rules':
                $this->setToPlot(self::toPlot_rules);
                break;

            default:
                throw new \Exception("Can't handle @group: '$g'");
        }

        $ret = [];
        $groups = [];
        $selection = [
            'group',
            'qualifiers',
            'partitioning',
            'parallel'
        ];

        foreach ($csvFiles as $csvFile) {
            $dirName = \basename(\dirname($csvFile));
            $delements = \Help\Plotter::extractDirNameElements($dirName);
            $elements = \Help\Arrays::subSelect($delements, $selection);

            $k = \Help\Plotter::encodeDirNameElements($elements, '');

            $ret["$k$g"][] = $csvFile;
        }
        return $ret;
    }
}