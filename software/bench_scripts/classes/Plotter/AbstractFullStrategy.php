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

    private $conf = null;

    public function plot_getConfig(array $default = []): array
    {
        if ($this->conf !== null)
            return $this->conf;

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
        return $this->conf = $conf + $default + [
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
            $toPlot = $this->toPlot;

        $ret = [];

        foreach ($toPlot as $k => $v) {

            if (\is_int($k)) {
                list ($k, $v) = \explode(':', $v, 2);
            }
            $ret[] = [
                $k,
                $v
            ];
        }
        return $ret;
    }

    private function toPlotIndex(): array
    {
        $index = [];
        $i = 2;

        foreach ($this->toPlot() as $pair) {
            list ($name, $v) = $pair;
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

        foreach ($this->toPlot() as $pair) {
            list ($what, $times) = $pair;

            foreach (\explode(',', $times) as $time)
                $ret[] = "$what.$time";
        }
        return $ret;
    }

    private static function moreData(array $data, string $dirName): array
    {
        $delements = \Help\Plotter::extractDirNameElements($dirName);
        $rules = $delements['rules'];

        if (\preg_match("#^\((\d+\))#U", $rules, $matches))
            $rulesNbQueries = (int) $matches[1];

        $intended = $rulesNbQueries ?? - 1;
        $cleaned = $intended >= 0 ? $intended - $data['queries']['total'] : - 1;

        $data['rules'] = [
            'queries.nb.intended' => $intended,
            'queries.nb.cleaned' => $cleaned
        ];
        $data['filter.prefix']['total'] = (int) $delements['filter_prefix'];
        return self::morePartitionsData($data);
    }

    private static function morePartitionsData(array $data): array
    {
        $nbReformulations = $data['queries']['total'];
        $nbAnswers = $data['answers']['total'];
        $nbPartitions = $data['partitions']['total'] ?? 1;

        if (isset($data['partitions'])) {
            $partitionsData = $data['partitions'];
            $patitionsNbQueries = \explode(',', $partitionsData['each.queries.total']);
            $uniqueNbQueries = \array_unique($patitionsNbQueries);
            $allPartitionsSameQueries = \count($uniqueNbQueries) == 1;
            $nbPartitionsHavingQueries = $data['partitions.used']['total'];

            $data['partitions.infos']['all.sameQueries'] = $allPartitionsSameQueries;

            if ($allPartitionsSameQueries)
                $nbReformulations /= $nbPartitions;

            $data['partitions.infos']['all.queries.nb'] = $nbReformulations;
        } else {
            $data['partitions.infos'] = [
                'all.sameQueries' => 1,
                'all.queries.nb' => $nbReformulations
            ];
        }
        return $data;
    }

    public function sortDataLines(array &$data): void
    {}

    public function getDataLine(string $csvPath = ''): array
    {
        $ret = [];
        $plotConfig = $this->plot_getConfig();
        $data = \is_file($csvPath) ? \CSVReader::read($csvPath) : [];

        $makeXTics = $plotConfig['plot.xtic'] ?? [
            $this,
            'makeXTic'
        ];
        $dirName = \basename(\dirname($csvPath));
        $partitionsData = [];

        $data = self::moreData($data, $dirName);

        $testData[$dirName] = $data;

        $query = \basename($csvPath, '.csv');
        $xtic = $makeXTics($testData, $query, $partitionsData ?? []);

        $ret[] = $xtic;

        $globalRange = &$this->getRange();
        $gyMin = &$globalRange[0];
        $gyMax = &$globalRange[1];

        $range = &$this->getRange($csvPath);
        $yMin = &$range[0];
        $yMax = &$range[1];

        foreach ($this->toPlot() as $pair) {
            list ($what, $times) = $pair;

            foreach (explode(',', $times) as $time) {

                foreach (explode('|', $what) as $what) {
                    $v = \Benchmark::getOneMeasure($data, $what, $time);

                    if (\is_numeric($v)) {
                        $v = (int) $v;

                        $ret[] = $v;
                        break;
                    } elseif ($v !== null) {
                        $ret[] = $v;
                        break;
                    }
                }
                if (\is_numeric($v)) {
                    $gyMax = \max($gyMax, $v);
                    $gyMin = \min($gyMin, $v);
                    $yMax = \max($yMax, $v);
                    $yMin = \min($yMin, $v);
                } elseif (! isset($v)) {
                    $ret[] = - 10000;
                }
            }
        }
        return $ret;
    }

    private const summaryScore = [
        '' => 0,
        'depth' => 0,
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
        $score *= 1000;
        $score += self::summaryScore[$summary] * 100;
        $score += (int) isset($elements['filter_prefix']);

        return $score;
    }

    public static function makeXTic_numbers($testData, $query, $partitionsData)
    {
        foreach ($testData as $dirName => $data)
            break;

        $ret = [];
        $nbAnswers = $data['answers']['total'];
        $nbPartitions = $data['partitions']['total'] ?? 1;
        $nbPartitionsHavingQueries = $data['partitions.used']['total'] ?? 1;
        $allPartitionsSameQueries = $data['partitions.infos']['all.sameQueries'];
        $nbReformulations = $data['partitions.infos']['all.queries.nb'] ?? $data['queries']['total'];

        $ret[] = "$nbReformulations";
        // $nbAnswers = $showNbAnswers ? ",$nbAnswers" : null;
        $font = "/=10";

        if ($allPartitionsSameQueries && $nbPartitions > 1)
            $ret[] = "\{$font\[$nbPartitions]}";
        elseif (! empty($nbReformulations) && ! $allPartitionsSameQueries)
            $ret[] = "\{$font\[$nbPartitionsHavingQueries/$nbPartitions]}";

        return \implode('', $ret);
    }

    public static function makeXTic($testData, $query, $partitionsData)
    {
        return self::makeXTic_clean('', false, true)($testData, $query, $partitionsData);
    }

    public static function makeXTic_fromDirName(string $dirName, string $testName = "", int $nbPartitions = 1, bool $showRules = false)
    {
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

        // if ($parallel)
        // $parallel = "[parallel]";

        if (empty($summary) && empty($testName))
            $summary = 'depth';
        if (! empty($summary) && ! empty($testName))
            $summary = "($summary)";
        if (! empty($filterPrefix)) {

            // if ($filterPrefix != 5)
            $filterPrefix = "-$filterPrefix$";
            // else
            // $filterPrefix = "$";
        }

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
        $dnbartition = "";

        if (! empty($partition))
            $partition = ".$partition";
        elseif ($nbPartitions > 1)
            $dnbartition = "[p$nbPartitions]";

        {
            if ($parallel)
//                 $parall1 = '\\|\\| ';
                $parall1 = 'parall ';
            else
                $parall1 = "";

            $parallel = "";
        }

        return "$parall1$dnbartition$partitioning$partition$pid$summary$rules$filterPrefix$parallel";
    }

    public static function makeXTic_clean(string $testName = "", bool $showNbAnswers = false, bool $showRules = false)
    {
        return function ($testData, $query) use ($testName, $showNbAnswers, $showRules) {

            foreach ($testData as $dirName => $data)
                break;

            $nbAnswers = $data['answers']['total'];
            $nbPartitions = $data['partitions']['total'] ?? 1;
            $nbPartitionsHavingQueries = $data['partitions.used']['total'] ?? 1;
            $allPartitionsSameQueries = $data['partitions.infos']['all.sameQueries'];
            $nbReformulations = $data['partitions.infos']['all.queries.nb'] ?? $data['queries']['total'];

            $xtic = self::makeXTic_fromDirName($dirName, $testName, $nbPartitions, $showRules);
            $nbAnswers = $showNbAnswers ? ",$nbAnswers" : null;

            if (! empty($nbReformulations) && ! $allPartitionsSameQueries && $nbPartitionsHavingQueries > 1)
                $nbReformulations = "$nbReformulations\[$nbPartitionsHavingQueries\]";

            if (! empty($nbAnswers) || ! empty($nbReformulations))
                $infos = "($nbReformulations$nbAnswers)";
            else
                $infos = '';

            return "$xtic$infos";
        };
    }
}