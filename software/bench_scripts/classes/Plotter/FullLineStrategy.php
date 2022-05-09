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
        'ylabel' => 'answering time'
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

    public function getPlotConfig(): array
    {
        return self::plotConfig;
    }

    private const SELECT_ELEMENTS = [
        'full_group',
        'summary'
    ];

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
        $ret = \Help\Arrays::subSelect($ret, $groups[0]);
        return $ret;
    }
}