<?php
namespace Plotter;

abstract class AbstractFullPlotter implements IFullPlotter
{

    private array $testsMeasures = [];

    protected \Plot $plot;

    protected function __construct(\Plot $plot)
    {
        $this->plot = $plot;
    }

    public function getPlot(): \Plot
    {
        return $this->plot;
    }

    public function getConfig(array $default = []): array
    {
        return $this->getPlot()->getConfigFor($this, $default);
    }

    protected function cleanCurrentDir(string ...$globs)
    {
        if (empty($globs))
            $globs = [
                '*.dat'
            ];
        foreach ($globs as $glob)
            foreach ($g = \glob($glob) as $file)
                \unlink($file);
    }

    public function getTestMeasures(string $test): \Measures
    {
        return $this->plot->getTestMeasures($test);
    }

    public function getTestMeasuresAverage(string $test, array $config = []): \Measures
    {
        $m = $this->getTestMeasures($test)->average($config + $this->getConfig());
        return $m->setMeasures(self::moreData($m->getMeasures(), \dirname($test)));
    }

    public static function moreData(array $data, string $dirName): array
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
        $data['dir.elements'] = $delements;
        return self::morePartitionsData($data);
    }

    public static function morePartitionsData(array $data): array
    {
        $nbReformulations = $data['queries']['total'];

        if (isset($data['partitions'])) {
            $partitionsData = $data['partitions'];
            $partitionsNbQueries = \Help\Arrays::decode($partitionsData['each.queries.total']);
            $partitionsNbQueries = \array_filter($partitionsNbQueries); // Delete 0 queries values
            $uniqueNbQueries = \array_unique($partitionsNbQueries);
            $allPartitionsSameQueries = \count($uniqueNbQueries) == 1;

            $nbPartitionsHavingQueries = //
            $data['partitions']['used'] ?? //
            $data['partitions.used']['total']; //

            $data['partitions.infos']['all.sameQueries'] = $allPartitionsSameQueries;

            if ($allPartitionsSameQueries)
                $allQueries = $nbReformulations / $nbPartitionsHavingQueries;

            $data['partitions.infos'] += [
                'all.queries.nb' => $allQueries ?? $nbReformulations,
                'used.queries.avg' => $nbReformulations / $nbPartitionsHavingQueries
            ];
        } else {
            $data['partitions.infos'] = [
                'all.sameQueries' => 1,
                'all.queries.nb' => $nbReformulations,
                'used.queries.avg' => $nbReformulations
            ];
        }
        return $data;
    }
}
