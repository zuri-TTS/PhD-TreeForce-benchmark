<?php
namespace Plotter;

final class FullParallelPartitioningPlotter extends AbstractFullPlotter
{

    public function __construct(\Plot $plot)
    {
        parent::__construct($plot);
    }

    public function getId(): string
    {
        return 'partitioning_parallel';
    }

    public function getProcessType(): string
    {
        return \Plot::PROCESS_FULL;
    }

    public function plot(array $tests): void
    {
        // $this->cleanCurrentDir();
        $this->writeMeasures($tests);
    }

    // ========================================================================
    private function prepareMeasures(string $test): array
    {
        $selection = [
            'group',
            'rules',
            'partitioning',
            'qualifiers'
        ];
        $config = $this->getConfig();
        $testsMeasures = $this->getTestMeasures($test)->getMeasures();
        $elements = \Help\Plotter::extractDirNameElements(\dirname($test));
        $partitionDataGroup = "{$elements['group']}[{$elements['qualifiers']}]/{$elements['partition']}";
        $newElements = \Help\Arrays::subSelect($elements, $selection);
        $each = [];

        foreach ($testsMeasures as $measures) {
            $partitions = [];

            foreach ($measures as $mGroupName => $gmeasures) {
                // Is partition
                if (\preg_match("#(.+)/(.+)$#", $mGroupName, $matches)) {
                    $partitions[] = $matches[1];
                    $groupName = $matches[2];

                    foreach ($gmeasures as $mname => $v)
                        $each["each.$groupName.$mname"][] = $v;
                } else {
                    $newData[$mGroupName] = $gmeasures;
                }
            }
            $partitions = \array_values(\array_unique($partitions));
            $nbUsed = 0;
            $hasAnswers = false;

            foreach ($partitions as $partitionName) {

                if ((int) $measures["$partitionName/queries"]['total'] > 0)
                    $nbUsed ++;
                if ((int) $measures["$partitionName/answers"]['total'] > 0)
                    $hasAnswers = true;
            }
            $newData += [
                'partitions' => [
                    'total' => \count($partitions),
                    'used' => $nbUsed,
                    'hasAnswer' => $hasAnswers
                ] + $each,
                'partition' => [
                    'name' => \array_map(fn ($e) => explode('.', $e)[1], $partitions)
                ]
            ];
            $ret[] = $newData;
        }
        return $ret;
    }

    private function writeMeasures(array $testGroups)
    {
        foreach ($testGroups as $test) {
            \wdPush("..");
            $aggregationMeasures = $this->prepareMeasures($test);
            \wdPop();
            $query = \basename($test);
            $basePath = \dirname($test);
            $basePath = \Help\Plotter::encodeDirNameElements(\Help\Plotter::extractDirNameElements($basePath), '[%s]');

            if (! \is_dir($basePath))
                \mkdir($basePath);

            $i = 1;
            foreach ($aggregationMeasures as $measures) {
                $fp = \fopen("$basePath/{$query}_measures-$i.txt", "w");
                \Measures::writeAsIni($measures, $fp);
                \fclose($fp);
                $i ++;
            }
        }
    }
}
