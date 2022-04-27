<?php
namespace Plotter;

abstract class AbstractFullStrategy implements IFullPlotterStrategy
{

    private array $ranges = [];

    protected array $toPlot;

    protected array $stackedMeasuresToPlot;

    protected function __construct()
    {}

    public function setToPlot(array $toPlot): AbstractFullStrategy
    {
        $this->toPlot = $toPlot;
        return $this;
    }

    public function setStackedMeasuresToPlot(array $stackedMeasures): AbstractFullStrategy
    {
        $this->stackedMeasuresToPlot = $stackedMeasures;
        return $this;
    }

    public function plot_getConfig(): array
    {
        return [
            'plot.yrange' => 'global',
            'plot.yrange.step' => 10,
            'logscale' => true
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

    public function plot_getStackedMeasures(): array
    {
        return $this->stackedMeasuresToPlot;
    }

    // ========================================================================
    public function getDataHeader(): array
    {
        $ret[] = 'test';

        foreach ($this->toPlot as $what => $time)
            $ret[] = "$what.$time";

        return $ret;
    }

    public function getDataLine(string $csvPath = ''): array
    {
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
        $xtic = $this->makeXTic($dirName, $nbReformulations, $nbAnswers);
        $ret[] = $xtic;

        $globalRange = &$this->getRange();
        $gyMin = &$globalRange[0];
        $gyMax = &$globalRange[1];

        $range = &$this->getRange($csvPath);
        $yMin = &$range[0];
        $yMax = &$range[1];

        foreach ($this->toPlot as $what => $time) {

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
        'path' => 2
    ];

    protected function sortScore(string $dirName): int
    {
        $score = 0;
        $elements = \Help\Plotter::extractDirNameElements($dirName);
        $partitioning = $elements['partitioning'];
        $pid = $elements['partition_id'];

        if (! empty($partitioning)) {
            $score += 10;

            if ($partitioning[0] !== 'L')
                $score += 10;
            if (! empty($pid))
                $score += 5;
        }

        $summary = $elements['summary'];
        $score += self::summaryScore[$summary] * 1;

        if ($elements['parallel'])
            $score += 100;

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
}