<?php
namespace Plotter;

abstract class AbstractFullStrategy implements IFullPlotterStrategy
{

    private int $yMax = 0;

    private int $yMin = PHP_INT_MAX;

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

    public function plot_getYRange(): array
    {
        return [
            $this->yMin,
            $this->yMax
        ];
    }

    public function plot_getStackedMeasures(): array
    {
        return $this->stackedMeasuresToPlot;
    }

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

        foreach ($this->toPlot as $what => $time) {

            foreach (explode('|', $what) as $what) {
                $v = (int) \Help\Arrays::follow($data, [
                    $what,
                    $time
                ], 0);

                if ($v !== 0) {
                    $this->yMax = \max($this->yMax, $v);
                    $this->yMin = \min($this->yMin, $v);
                    $ret[] = $v;
                    break;
                }
            }
            
            if($v === 0)
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
        $partition = $elements['partition'];

        if (! empty($partition)) {
            $score += 10;

            if ($partition[0] !== 'L')
                $score += 10;
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
        $partition = $elements['partition'];
        $parallel = $elements['parallel'];

        if ($parallel)
            $parallel = "[parallel]";
        if (! empty($summary))
            $summary = "($summary)";
        if (! empty($partition))
            $partition = ".$partition";

        return "$group$partition$summary$parallel($nbReformulations,$nbAnswers)";
    }
}