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

    function groupCSVFiles(array $csvFiles): array
    {
        $queries = \array_unique(\array_map(fn ($p) => \basename($p, '.csv'), $csvFiles));
        $dirs = \array_unique(\array_map(fn ($p) => \dirname($p), $csvFiles));
        $groups = \array_map(function ($p) {
            $dirName = \basename($p);
            $elements = \Help\Plotter::extractDirNameElements($dirName);
            return $elements['full_group'];
        }, $dirs);
        $groups = \array_unique($groups, SORT_REGULAR);
        \natcasesort($groups);

        foreach ($groups as $group) {
            $regex = "#^\[$group#";
            $gdirs = \array_filter($dirs, fn ($d) => \preg_match($regex, \basename($d)));
            $gscores = \array_map(fn ($d) => $this->sortScore(\basename($d)), $gdirs);
            $gdirs = \array_map(null, $gscores, $gdirs);

            \usort($gdirs, function ($a, $b) {

                if ($a[0] !== $b[0])
                    return $a[0] - $b[0];

                return \strnatcasecmp($a[1], $b[1]);
            });
            $gdirs = \array_column($gdirs, 1);

            $ret[$group] = \array_values(\array_filter($csvFiles, fn ($csv) => \preg_match($regex, \basename(\dirname($csv)))));
        }
        return $ret;
    }
}