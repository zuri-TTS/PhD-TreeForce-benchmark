<?php
namespace Plotter;

final class FullLineStrategy extends AbstractFullStrategy
{

    private const toPlot = [
        'partitions.infos' => 'all.queries.nb',
        'stats.db.time|threads.time' => 'r'
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
        $this->setToPlot(self::toPlot);
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

    function groupCSVFiles(array $csvFiles): array
    {
        $ret = [];
        $groups = [];
        $selection = [
            'group',
            'qualifiers',
            // 'rules',
            'partitioning',
            'partition',
            'parallel'
        ];

        foreach ($csvFiles as $csvFile) {
            $dirName = \basename(\dirname($csvFile));
            $elements = \Help\Plotter::extractDirNameElements($dirName);
            $groupElements = \Help\Arrays::subSelect($elements, $selection);

            $group = $elements['full_group'];

            $k = \Help\Plotter::encodeDirNameElements($groupElements, '');
            $ret[$k][] = $csvFile;
            $groups[$k] = $this->sortScore($elements);
        }
        $scoreKeys = \Help\Arrays::flipKeys($groups);
        sort($groups);
        $scoreKeys = \Help\Arrays::subSelect($scoreKeys, $groups);
        $groups = \array_map(null, ...$scoreKeys);
        return $ret;
    }
}