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

    public function plot(array $csvPaths): void
    {
        $this->cleanCurrentDir();
        $this->writeCsv($csvPaths);
    }

    // ========================================================================
    private static function prepareMeasures(string $csvFile): array
    {
        $selection = [
            'group',
            'rules',
            'partitioning',
            'qualifiers'
        ];

        $data = \CSVReader::read($csvFile);
        $elements = \Help\Plotter::extractDirNameElements(\basename(\dirname($csvFile)));
        $partitionDataGroup = "{$elements['group']}[{$elements['qualifiers']}]/{$elements['partition']}";
        $newElements = \Help\Arrays::subSelect($elements, $selection);
        $newData = [];

        foreach ($data as $k => $items) {

            // Is partition
            if (\preg_match("#(.+)/(.+)$#", $k, $matches)) {
                $partitioning = $matches[1];
                $valGroup = $matches[2];
                $partition = \preg_split('#[/\.]#', $partitioning, 2)[1];
                $newElements['partition'] = $partition;
                $newData[\Help\Plotter::encodeDirNameElements($newElements, '') . "/$valGroup"] = $items;
            } else
                $newData[$k] = $items;
        }
        $partitionsStats = FullPartitioningPlotter::extractPartitionsStats($newData);
        return $newData + $partitionsStats;
    }

    private static function writeCsv(array $csvFiles)
    {
        foreach ($csvFiles as $file) {

            if (! \is_file($file))
                continue;

            $fname = \basename($file);
            $prepareMeasures = self::prepareMeasures($file);
            $basePath = \basename(\dirname($file));
            $basePath = \Help\Plotter::encodeDirNameElements(\Help\Plotter::extractDirNameElements($basePath), '[%s]');

            if (! \is_dir($basePath))
                \mkdir($basePath);

            $csvFile = "$basePath/$fname";
            \CSVReader::write($csvFile, $prepareMeasures);
        }
    }
}
