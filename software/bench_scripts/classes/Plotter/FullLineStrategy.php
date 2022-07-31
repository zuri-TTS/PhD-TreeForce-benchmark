<?php
namespace Plotter;

final class FullLineStrategy extends AbstractFullStrategy
{

    private const toPlot = [
        'queries' => 'total',
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

        foreach ($csvFiles as $csvFile) {
            $dirName = \basename(\dirname($csvFile));
            $elements = \Help\Plotter::extractDirNameElements($dirName);
            $group = $elements['full_group'];

            $summary = $elements['summary'];
            $parallel = $elements['parallel'] ? 'parallel' : '';

            if (! empty($summary))
                $summary = "[$summary]";
            if (! empty($parallel))
                $parallel = "[$parallel]";

            $k = "$group$summary$parallel";
            $ret[$k][] = $csvFile;
            $groups[$k] = $this->sortScore($elements);
        }
        $scoreKeys = \Help\Arrays::flipKeys($groups);
        sort($groups);
        $scoreKeys = \Help\Arrays::subSelect($scoreKeys, $groups);
        $groups = \array_map(null, ...$scoreKeys);
        $ret = \Help\Arrays::subSelect($ret, (array) $groups[0]);
        return $ret;
    }
}