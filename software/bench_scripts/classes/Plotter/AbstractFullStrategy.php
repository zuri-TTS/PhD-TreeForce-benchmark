<?php
namespace Plotter;

abstract class AbstractFullStrategy implements IFullPlotterStrategy
{

    private array $ranges = [];

    protected array $toPlot;

    protected array $stackedMeasuresToPlot_default;

    protected FullPlotter $plotter;

    protected function __construct()
    {}

    public function setPlotter(FullPlotter $plotter): void
    {
        $this->plotter = $plotter;
    }

    public function getPlotter(): FullPlotter
    {
        return $this->plotter;
    }

    public function setToPlot(array $toPlot): AbstractFullStrategy
    {
        $this->toPlot = $toPlot;
        return $this;
    }

    public function setStackedMeasuresToPlot(array $stackedMeasures): AbstractFullStrategy
    {
        $this->stackedMeasuresToPlot_default = $stackedMeasures;
        return $this;
    }

    public function plot_getConfig(array $default = []): array
    {
        static $conf = null;

        if ($conf !== null)
            return $conf;

        $wd = getcwd();
        $path_ex = explode(DIRECTORY_SEPARATOR, $wd);
        $PARENT = [];
        $conf = [];

        for ($i = 1, $c = \count($path_ex); $i <= $c; $i ++) {
            $path = \implode(DIRECTORY_SEPARATOR, \array_slice($path_ex, 0, $i));
            $confFiles = [
                "$path/full.php",
                "$path/full_{$this->getID()}.php"
            ];

            foreach ($confFiles as $confFile) {

                if (\is_file($confFile)) {
                    $conf = \array_merge($conf, include $confFile);
                    $PARENT = $conf;
                }
            }
        }

        return $conf += $default + [
            'plot.yrange' => 'global',
            'plot.yrange.display' => false,
            'plot.pattern.offset' => 0,
            'plot.legend' => true,
            'plot.legend.w' => 200,
            'plot.ytics.step' => 1,
            'plot.ytics.nb' => 1,
            'plot.xtic' => null, // function
            'plot.title' => null, // function
            'plot.format.y' => "%gs",
            'multiplot.title' => true,
            'queries' => null, // array,
            'query.dat.file' => null, // function
            'measure.div' => 1000
        ];
    }

    // ========================================================================
    private const MAX_RANGE = [
        PHP_INT_MAX,
        0
    ];

    private function &getRange(string $csvPath = ''): array
    {
        if (! isset($this->range[$csvPath]))
            $this->range[$csvPath] = self::MAX_RANGE;

        return $this->range[$csvPath];
    }

    public function plot_getYRange(string ...$csvPath): array
    {
        if (empty($csvPath))
            $csvPath = [
                ''
            ];

        if (\count($csvPath) === 1)
            return $this->range[$csvPath[0]] ?? self::MAX_RANGE;

        return \array_reduce($csvPath, function ($a, $b) {
            list ($bmin, $bmax) = $this->range[$b];
            return [
                \min($a[0], $bmin),
                \max($a[1], $bmax)
            ];
        }, self::MAX_RANGE);
    }

    private function toPlot()
    {
        $plotter = $this->getPlotter();
        $plotConfig = $plotter->plot_getConfig();
        $toPlot = $plotConfig['toPlot'] ?? null;

        if (null === $toPlot)
            return $this->toPlot;

        return $toPlot;
    }

    private function toPlotIndex(): array
    {
        $index = [];
        $i = 2;

        foreach ($this->toPlot() as $name => $v) {
            $k = \explode('|', $name);

            foreach ($k as $k)
                $index[$k] = $i;

            $i ++;
        }
        return $index;
    }

    public function plot_getStackedMeasures(array $measures = []): array
    {
        if (empty($measures))
            return $this->stackedMeasuresToPlot_default;

        $ret = [];
        $toPlotIndex = $this->toPlotIndex();

        foreach ($measures as $key => $stack) {

            if (is_string($stack)) {

                if (is_int($key))
                    $key = $stack;

                $stack = [
                    $key => $stack
                ];
            }
            $s = [];

            foreach ($stack as $measure => $displayName) {
                $i = $toPlotIndex[$measure];
                $s[$i] = $displayName;
            }
            $ret[] = $s;
        }
        return $ret;
    }

    // ========================================================================
    public function getDataHeader(): array
    {
        $ret[] = 'test';

        foreach ($this->toPlot() as $what => $time)
            $ret[] = "$what.$time";

        return $ret;
    }

    public function getDataLine(string $csvPath = ''): array
    {
        $plotConfig = $this->plot_getConfig();
        $data = \is_file($csvPath) ? \CSVReader::read($csvPath) : [];

        $nbReformulations = \Help\Arrays::follow($data, [
            'queries',
            'total'
        ], - 1);
        $nbAnswers = \Help\Arrays::follow($data, [
            'answers',
            'total'
        ], - 1);

        $dirName = \basename(\dirname($csvPath));
        $makeXTics = $plotConfig['plot.xtic'] ?? [
            $this,
            'makeXTic'
        ];
        $xtic = $makeXTics($dirName, $nbReformulations, $nbAnswers);
        $ret[] = $xtic;

        $globalRange = &$this->getRange();
        $gyMin = &$globalRange[0];
        $gyMax = &$globalRange[1];

        $range = &$this->getRange($csvPath);
        $yMin = &$range[0];
        $yMax = &$range[1];

        foreach ($this->toPlot() as $what => $time) {

            foreach (explode('|', $what) as $what) {
                $v = (int) \Help\Arrays::follow($data, [
                    $what,
                    $time
                ], 0);

                if ($v !== 0) {
                    $ret[] = $v;
                    break;
                }
            }
            $gyMax = \max($gyMax, $v);
            $gyMin = \min($gyMin, $v);
            $yMax = \max($yMax, $v);
            $yMin = \min($yMin, $v);

            if ($v === 0)
                $ret[] = 0;
        }
        return $ret;
    }

    private const summaryScore = [
        '' => 0,
        'key-type' => 1,
        'label' => 1,
        'path' => 2
    ];

    protected const SCORE_ELEMENTS = [
        'partitioning',
        'partition_id',
        'parallel',
        'summary'
    ];

    protected function sortScore($dirNameOrElements): int
    {
        if (is_string($dirNameOrElements))
            $elements = \Help\Plotter::extractDirNameElements($dirNameOrElements);
        else
            $elements = (array) $dirNameOrElements;

        $score = 0;
        $partitioning = $elements['partitioning'];
        $pid = $elements['partition_id'];

        if (! empty($partitioning)) {
            $score += 10;

            if ($partitioning[0] !== 'L')
                $score += 100;
            if (! empty($pid))
                $score += 5;
        }

        if ($elements['parallel'])
            $score += 100;

        if ($score >= 100) {
            $score = 100;

            if (! empty($partitioning)) {
                $score += 10;

                if ($partitioning[0] !== 'L') {
                    $score += 10;

                    if ($elements['parallel'])
                        $score += 10;
                }
                if (! empty($pid))
                    $score += 5;
            }
        }
        $summary = $elements['summary'];
        $score += self::summaryScore[$summary] * 2;
        $score += (int)isset($elements['filter_prefix']);

        return $score;
    }

    private function makeXTic(string $dirName, int $nbReformulations, int $nbAnswers)
    {
        $elements = \Help\Plotter::extractDirNameElements($dirName);
        $group = $elements['group'];
        $summary = $elements['summary'];
        $partition = $elements['full_partition'];
        $parallel = $elements['parallel'];
        $pid = $elements['partition_id'];

        if (! empty($pid))
            $pid = "($pid)";
        if ($parallel)
            $parallel = "[parallel]";
        if (! empty($summary))
            $summary = "($summary)";
        if (! empty($partition))
            $partition = ".$partition";

        return "$group$partition$pid$summary$parallel($nbReformulations,$nbAnswers)";
    }

    public static function makeXTic_clean(string $testName = "test", bool $showNbAnswers = false)
    {
        return function ($dirName, $nbReformulations, $nbAnswers) use ($testName, $showNbAnswers) {
            $elements = \Help\Plotter::extractDirNameElements($dirName);
            $group = $elements['group'];
            $summary = $elements['summary'];
            $partitioning = $elements['partitioning'];
            $partition = $elements['partition'];
            $parallel = $elements['parallel'];
            $pid = $elements['partition_id'];
            $filterPrefix = $elements['filter_prefix'];

            if ($summary == 'key-type')
                $summary = 'label';

            if (! empty($partitioning)) {

                if (! empty($pid))
                    $pid = "($pid)";
            } else
                $pid = '';

            if ($parallel)
                $parallel = "[parallel]";
            if (! empty($summary))
                $summary = "($summary)";
            if (! empty($filterPrefix))
                $filterPrefix = "[vprefix]";

            switch ($partitioning) {
                case "":
                    $partitioning = $testName;
                    break;
                case "Lcolls":
                    $partitioning = "logical";
                    break;
                case "colls":
                    $partitioning = "physical";
                    break;
                default:
                    $partitioning = "Error:$partitioning";
            }
            $nbAnswers = $showNbAnswers ? ",$nbAnswers" : null;

            if (! empty($partition))
                $partition = ".$partition";

            return "$partitioning$partition$pid$summary$filterPrefix$parallel($nbReformulations$nbAnswers)";
        };
    }
}