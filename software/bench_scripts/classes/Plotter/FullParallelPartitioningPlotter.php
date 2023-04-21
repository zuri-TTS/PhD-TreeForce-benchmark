<?php
namespace Plotter;

final class FullParallelPartitioningPlotter extends AbstractFullPlotter
{

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
        $this->writeCsv($tests);
    }

    // ========================================================================
    private static function prepareMeasures(string $test): array
    {
        $selection = [
            'group',
            'rules',
            'partitioning',
            'qualifiers'
        ];

        $data = \Measures::loadTestMeasures($test);
        $elements = \Help\Plotter::extractDirNameElements(\dirname($test));
        $partitionDataGroup = "{$elements['group']}[{$elements['qualifiers']}]/{$elements['partition']}";
        $newElements = \Help\Arrays::subSelect($elements, $selection);
        $newData = [];

        foreach ($data as $k => $items) {

            // Is partition
            if (\preg_match("#(.+)/(.+)$#", $k, $matches)) {
                $partitioning = $matches[1];
                $valGroup = $matches[2];

                // Physic
                if (preg_match("#/(.+)$#U", $partitioning, $matches))
                    $partition = $matches[1];
                // Logic (Mongo::getcollectioname() . prefix)
                else
                    $partition = \explode('].', $partitioning, 2)[1];

                $newElements['partition'] = $partition;
                $newKey = \Help\Plotter::encodeDirNameElements($newElements, '') . "/$valGroup";
                $newData[$newKey] = $items;
            } else
                $newData[$k] = $items;
        }
        $partitionsStats = FullPartitioningPlotter::extractPartitionsStats($newData);
        return $newData + $partitionsStats;
    }

    private static function writeCsv(array $tests)
    {
        foreach ($tests as $test) {

            // if (! \is_file($test))
            // continue;

            $fname = \basename($test);
            \wdPush("..");
            $prepareMeasures = self::prepareMeasures($test);
            \wdPop();
            $basePath = \basename(\dirname($test));
            $basePath = \Help\Plotter::encodeDirNameElements(\Help\Plotter::extractDirNameElements($basePath), '[%s]');

            if (! \is_dir($basePath))
                \mkdir($basePath);

            $csvFile = "$basePath/$fname.csv";
            \CSVReader::write($csvFile, $prepareMeasures);
        }
    }
}
