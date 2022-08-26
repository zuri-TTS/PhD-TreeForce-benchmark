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
            'plot.legend.w' => null,
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

        foreach ($this->toPlot() as $what => $times)
            foreach (\explode(',', $times) as $time)
                $ret[] = "$what.$time";

        return $ret;
    }

    public function sortDataLines(array &$data): void
    {}

    public function getDataLine(string $csvPath = ''): array
    {
        $plotConfig = $this->plot_getConfig();
        $data = \is_file($csvPath) ? \CSVReader::read($csvPath) : [];

        $nbReformulationsTrue = \Help\Arrays::follow($data, [
            'queries',
            'total.true'
        ], - 1);

        $makeXTics = $plotConfig['plot.xtic'] ?? [
            $this,
            'makeXTic'
        ];
        $dirName = \basename(\dirname($csvPath));
        $partitionsData = [];

        if (- 1 === $nbReformulationsTrue) {
            $elements = \Help\Plotter::extractDirNameElements($dirName);

            if (! empty($elements['partitioning'])) {
                // Count nb of partitions
                $partitionsName = [];

                foreach (\array_keys($data) as $group) {

                    if (! \str_starts_with($group, "{$elements['group']}"))
                        continue;

                    preg_match("#(.+)/.*$#", $group, $matches);
                    $partitionsName[$matches[1]] = null;
                }

                foreach ($partitionsName as $pname => $v)
                    $partitionsData[$pname] = [
                        'queries' => $data["$pname/queries"],
                        'answers' => $data["$pname/answers"]
                    ];
            }
        }

        $testData[$dirName] = [
            'queries' => $data["queries"],
            'answers' => $data["answers"]
        ];

        if (empty($partitionsData))
            $partitionsData = $testData;

        $nbPartitionsHavingQueries = 0;
        $nbPartitionsHavingAnswers = 0;

        \ksort($partitionsData);

        // Extract partitions data
        foreach ($partitionsData as $p) {

            if ($p['queries']['total'] > 0)
                $nbPartitionsHavingQueries ++;
            if ($p['answers']['total'] > 0)
                $nbPartitionsHavingAnswers ++;

            foreach ($p as $k => $v) {

                foreach ($v as $kk => $vv) {
                    @$data["partitions/$k"][$kk] .= "$vv,";

                    if (\is_numeric($vv) && $vv != 0)
                        @$data["partitions/$k.clean"][$kk] .= "$vv,";
                }
            }
        }
        unset($v);

        $data['partitions'] = [
            'total' => \count($partitionsData),
            'used' => $nbPartitionsHavingQueries,
            'hasAnswer' => $nbPartitionsHavingAnswers
        ];

        // Trim generated 'partitions/$k'
        foreach ($data as $k => $items) {
            if (! \str_starts_with($k, 'partitions/'))
                continue;

            foreach ($items as $kk => $v) {
                $data[$k][$kk] = \rtrim($v, ',');
            }
        }

        $query = \basename($csvPath, '.csv');
        $xtic = $makeXTics($testData, $query, $partitionsData);

        $ret[] = $xtic;

        $globalRange = &$this->getRange();
        $gyMin = &$globalRange[0];
        $gyMax = &$globalRange[1];

        $range = &$this->getRange($csvPath);
        $yMin = &$range[0];
        $yMax = &$range[1];

        foreach ($this->toPlot() as $what => $times) {

            foreach (explode(',', $times) as $time) {

                foreach (explode('|', $what) as $what) {

                    $v = \Help\Arrays::follow($data, [
                        $what,
                        $time
                    ], 0);

                    if (\is_numeric($v)) {
                        $v = (int) $v;

                        if ($v !== 0) {
                            $ret[] = $v;
                            break;
                        }
                    } elseif ($v !== '') {
                        $ret[] = $v;
                        break;
                    }
                }
                if (\is_numeric($v)) {
                    $gyMax = \max($gyMax, $v);
                    $gyMin = \min($gyMin, $v);
                    $yMax = \max($yMax, $v);
                    $yMin = \min($yMin, $v);

                    if ($v === 0)
                        $ret[] = 0;
                }
            }
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
        $score += (int) isset($elements['filter_prefix']);

        return $score;
    }

    public static function makeXTic_clean(string $testName = "", bool $showNbAnswers = false, bool $showRules = false)
    {
        return function ($testData, $query, $partitionsData) use ($testName, $showNbAnswers, $showRules) {

            foreach ($testData as $dirName => $data)
                break;

            $nbReformulations = $data['queries']['total'];
            $nbAnswers = $data['answers']['total'];
            $nbPartitions = \count($partitionsData);

            {
                $patitionsNbQueries = \Help\Arrays::columns($partitionsData, 'queries', 'total');
                $uniqueNbQueries = \array_unique($patitionsNbQueries);
                \sort($uniqueNbQueries);

                $allPartitionsSameQueries = \count($uniqueNbQueries) == 1;

                if ($allPartitionsSameQueries)
                    $nbReformulations /= $nbPartitions;

                $nbPartitionsHavingQueries = 0;

                foreach ($patitionsNbQueries as $nbq) {

                    if ($nbq != 0)
                        $nbPartitionsHavingQueries ++;
                }
            }
            $elements = \Help\Plotter::extractDirNameElements($dirName);
            $group = $elements['group'];
            $summary = $elements['summary'];
            $partitioning = $elements['partitioning'];
            $partition = $elements['partition'];
            $parallel = $elements['parallel'];
            $pid = $elements['partition_id'];
            $filterPrefix = $elements['filter_prefix'];

            if ($showRules) {
                $rules = $elements['rules'];

                if (\preg_match("#^\((.+)\)#U", $rules, $matches))
                    $rules = $matches[1];

                $rules = "/$rules";
            } else
                $rules = '';

            if ($summary == 'key-type')
                $summary = 'label';

            if (! empty($partitioning)) {

                if (! empty($pid) && $pid !== 'pid')
                    $pid = "($pid)";
                else
                    $pid = '';
            } else
                $pid = '';

            if ($parallel)
                $parallel = "[parallel]";

            if (empty($summary) && empty($testName))
                $summary = 'depth';
            if (! empty($summary) && ! empty($testName))
                $summary = "($summary)";
            if (! empty($filterPrefix))
                $filterPrefix = "[$]";

            switch ($partitioning) {
                case "":
                    $partitioning = $testName;
                    break;
                case "LPcolls":
                case "Lcolls":
                    $partitioning = "logic ";
                    break;
                case "colls":
                case "Pcolls":
                    $partitioning = "physic ";
                    break;
                default:
                    $partitioning = "(Error:$partitioning)";
            }
            $nbAnswers = $showNbAnswers ? ",$nbAnswers" : null;
            $dnbartition = "";

            if (! empty($partition))
                $partition = ".$partition";
            elseif ($nbPartitions > 1)
                $dnbartition = "[p$nbPartitions]";

            {
                if ($parallel)
                    $parall1 = '\\|\\| ';
                else
                    $parall1 = "";

                $parallel = "";
            }

            if (! empty($nbReformulations) && ! $allPartitionsSameQueries && $nbPartitionsHavingQueries > 1)
                $nbReformulations = "$nbReformulations\[$nbPartitionsHavingQueries\]";

            if (! empty($nbAnswers) || ! empty($nbReformulations))
                $infos = "($nbReformulations$nbAnswers)";
            else
                $infos = '';

            return "$parall1$dnbartition$partitioning$partition$pid$summary$rules$filterPrefix$parallel$infos";
        };
    }
}