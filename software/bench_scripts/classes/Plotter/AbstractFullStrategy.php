<?php
namespace Plotter;

abstract class AbstractFullStrategy implements IFullPlotterStrategy
{

    private array $range = [];

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

    private function getConfig(array $default = []): array
    {
        return $this->plotter->getConfig($default);
    }

    // ========================================================================
    private const MAX_RANGE = [
        PHP_INT_MAX,
        0
    ];

    private function &getRange(string $tests = ''): array
    {
        if (! isset($this->range[$tests]))
            $this->range[$tests] = self::MAX_RANGE;

        return $this->range[$tests];
    }

    public function plot_getYRange(array $testsMeasures): array
    {
        return self::MAX_RANGE;
        // TODO
        if (empty($tests))
            $tests = [
                ''
            ];

        if (\count($tests) === 1)
            return $this->getRange($tests[0]->getTestName());

        $tests = \array_map(fn ($t) => $this->plotter->getTestMeasures($t), $tests);
        return \array_reduce($tests, function ($a, $b) {
            list ($bmin, $bmax) = $this->getRange($b);
            return [
                \min($a[0], $bmin),
                \max($a[1], $bmax)
            ];
        }, self::MAX_RANGE);
    }

    private function toPlot()
    {
        $plotter = $this->getPlotter();
        $plotConfig = $this->getConfig();
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

    private function toPlotIndex()
    {
        $ret = [];
        $toPlot = $this->toPlot();
        // Offset in the .dat file
        $i = 2;

        foreach ($toPlot as $pair) {
            list ($group, $measure) = $pair;

            if (! isset($ret["$group:"]))
                $ret["$group:"] = $i;

            foreach (explode('|', $group) as $group)
                $ret["$group:$measure"] = $i;

            $i ++;
        }
        return $ret;
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

            foreach ($stack as $groupAndMeasure => $displayName) {
                list ($group, $measure) = explode(':', $groupAndMeasure, 2) + [
                    null,
                    null
                ];

                foreach (explode('|', $group) as $group) {

                    if (isset($toPlotIndex["$group:$measure"])) {
                        $i = $toPlotIndex["$group:$measure"];
                        break;
                    }
                }

                if (! isset($i))
                    fwrite(STDERR, "Invalid measure to plot '$groupAndMeasure'\n");
                else
                    $s[$i] = $displayName;
            }
            if (! empty($s))
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

    public function sortDataLines(array &$data): void
    {}

    public function getDataLine(\Measures $testMeasures): array
    {
        $ret = [];
        $test = $testMeasures->getTestName();
        $dirName = $testMeasures->getDirectoryName();
        $query = $testMeasures->getQueryName();
        $plotConfig = $this->getConfig();

        $makeXTics = $plotConfig['plot.xtic'] ?? [
            $this,
            'makeXTic'
        ];
        $partitionsData = [];

        $data = $testMeasures->getMeasures();
        $data = $data['measures'] + $data;
        unset($data['measures']);

        $testData[$dirName] = $data;

        $xtic = $makeXTics($testData, $query, $partitionsData ?? []);

        $ret[] = $xtic;

        $globalRange = &$this->getRange();
        $gyMin = &$globalRange[0];
        $gyMax = &$globalRange[1];

        $range = &$this->getRange($test);
        $yMin = &$range[0];
        $yMax = &$range[1];

        foreach ($this->toPlot() as $pair) {
            list ($what, $times) = $pair;

            foreach (explode(',', $times) as $time) {

                foreach (explode('|', $what) as $what) {
                    $v = \Measures::getOneMeasure($data, $what, $time);

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

    public static function getFun_makeXTic_numbers()
    {
        return fn ($a, $b, $c) => self::makeXTic_numbers($a, $b, $c);
    }

    public static function makeXTic_numbers($testData, $query, $partitionsData)
    {
        foreach ($testData as $dirName => $data)
            break;

        $ret = [];
        $nbAnswers = $data['answers']['total'];
        $nbPartitions = $data['partitions']['total'] ?? 1;
        $nbPartitionsHavingQueries = $data['partitions']['used'] ?? $data['partitions.used']['total'] ?? 1;
        $allPartitionsSameQueries = $data['partitions.infos']['all.sameQueries'];
        $nbReformulations = $data['partitions.infos']['all.queries.nb'] ?? $data['queries']['total'];

        $font = "/=10";

        if ($data['partitions.infos']['all.sameQueries']) {
            $ret[] = "$nbReformulations";
        } else {
            $ret[] = "{/=9(∑)}$nbReformulations";
            $avg = \round($data['partitions.infos']['used.queries.avg']);
            $ret[] = "{/=9(µ)}$avg";
        }

        if (isset($data['rules'])) {
            $rules = $data['rules']['queries.nb.intended'] ?? $nbReformulations;

            if ($rules != $nbReformulations)
                $ret[] = "\{$font\ /($rules)}";
        }
        // $nbAnswers = $showNbAnswers ? ",$nbAnswers" : null;

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
            $filterPrefix = "-$filterPrefix";
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
                // $parall1 = '\\|\\| ';
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