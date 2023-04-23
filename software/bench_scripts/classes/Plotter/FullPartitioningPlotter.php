<?php
namespace Plotter;

final class FullPartitioningPlotter extends AbstractFullPlotter
{

    public function __construct(\Plot $plot)
    {
        parent::__construct($plot);
    }

    public function getId(): string
    {
        return 'partitioning';
    }

    public function getProcessType(): string
    {
        return \Plot::PROCESS_FULL;
    }

    private $plotConfig = [];

    public function plot(array $tests): void
    {
        $this->cleanCurrentDir();
        $this->plotConfig = $this->plot->getConfigFor($this);
        $testGroups = self::groupTests($tests);
        $this->writeMeasures($testGroups);
    }

    // ========================================================================
    public static function groupTests(array $csvFiles): array
    {
        $queries = \array_unique(\array_map(fn ($p) => \basename($p), $csvFiles));
        $groupNames = \array_unique(\array_map(fn ($p) => \dirname($p), $csvFiles));
        \natcasesort($queries);

        $gdirs = [];

        foreach ($groupNames as $gname) {
            $delements = \Help\Plotter::extractDirNameElements($gname);
            unset($delements['partition']);
            unset($delements['full_group']);
            unset($delements['full_partition']);
            $dirGroup = \Help\Plotter::encodeDirNameElements($delements, '[%s]');

            foreach ($queries as $query)
                $ret[$query][$dirGroup][] = "$gname/$query";
        }
        return $ret;
    }

    // ========================================================================
    private function prepareMeasures(string $queryName, array $tests): array
    {
        $staticParts = [
            'bench'
        ];
        $elementsGroups = [
            'group',
            'rules',
            'partitioning',
            'partition',
            'qualifiers'
        ];
        $plotConfig = $this->plotConfig;
        $debug = $plotConfig['debug'];

        $nbColls = \count($tests);

        $infos['collections']['nb'] = $nbColls;

        $testPartitionsMeasures = [];
        $nbRepetitions = 0;

        // Post-process: get the number of repetitions
        foreach ($tests as $k => $test) {
            $allMeasures[$k] = $m = $this->plot->getTestMeasures($test);
            $nbRepetitions = \max($nbRepetitions, $m->getNbRepetitions());
        }

        // Get all the partitions measures of each test repetition
        for ($i = 0; $i < $nbRepetitions; $i ++) {

            foreach ($tests as $k => $test) {
                $measures = $allMeasures[$k];
                $elements = \Help\Plotter::extractDirNameElements($measures->getDirectoryName());
                $partitionDataGroup = \Help\Plotter::encodeDirNameElements(\Help\Arrays::subSelect($elements, $elementsGroups), '');

                $items = $measures->getMeasuresFromRepetition($i);
                unset($items['bench']);

                $items['partitions'] = [
                    'total' => 1,
                    'used' => (int) ($items['queries']['total'] > 0),
                    'hasAnswer' => (int) ($items['answers']['total'] > 0)
                ];
                $items['partition'] = [
                    'name' => $elements["partition"]
                ];

                // Make `each` categories
                foreach ($items as $groupName => $gmeasures) {
                    if ($groupName == "measures")
                        continue;

                    foreach ($gmeasures as $mname => $v)
                        $items['partitions']["each.$groupName.$mname"] = [
                            $v
                        ];
                }
                $testPartitionsMeasures[$i][$partitionDataGroup] = $items;
            }

            if ($debug) {
                $fp = \fopen("full_partitioning/{$queryName}_partitions-measures.txt", "w");
                $partitionsIni = \Help\Arrays::prefixSubItemsWithKey($testPartitionsMeasures[$i], ':');
                \Measures::writeAsIni($partitionsIni, $fp);
                \fclose($fp);
            }
        }

        // Sum partitions' results
        $sumTests = [];
        for ($i = 0; $i < $nbRepetitions; $i ++) {
            $measures = \array_reduce($testPartitionsMeasures[$i], function ($a, $b) {
                if (empty($a))
                    return $b;

                foreach ($a as $group => $values) {
                    $isTimeMeasure = $group === 'measures';

                    foreach ($values as $k => $av) {

                        if (! isset($b[$group][$k]))
                            $v = $av;
                        else {
                            $bv = $b[$group][$k];

                            if ($isTimeMeasure)
                                $v = \Measures::sumStringTimeMeasures($av, $bv);
                            elseif (\is_numeric($av) && \is_numeric($bv))
                                $v = $av + $bv;
                            elseif (\is_array($av) && \is_array($bv))
                                $v = \array_merge($av, $bv);
                            else
                                $v = "$av,$bv";
                        }
                        $a[$group][$k] = $v;
                    }
                }

                return $a;
            }, []);
            $sumTests[$i] = $measures + $infos;
        }
        return $sumTests;
    }

    private function writeMeasures(array $testGroups): void
    {
        foreach ($testGroups as $query => $groups) {

            foreach ($groups as $groupName => $tests) {
                \wdPush('..');
                $aggregationMeasures = $this->prepareMeasures($query, $tests);
                \wdPop();

                if (! \is_dir($groupName))
                    \mkdir($groupName);

                $i = 1;
                foreach ($aggregationMeasures as $measures) {
                    $fp = \fopen("$groupName/{$query}_measures-$i.txt", "w");
                    \Measures::writeAsIni($measures, $fp);
                    \fclose($fp);
                    $i ++;
                }
            }
        }
    }
}
