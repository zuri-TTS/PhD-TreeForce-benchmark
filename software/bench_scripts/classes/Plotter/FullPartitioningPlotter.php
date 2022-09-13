<?php
namespace Plotter;

final class FullPartitioningPlotter extends AbstractFullPlotter
{

    public function getId(): string
    {
        return 'partitioning';
    }

    public function getProcessType(): string
    {
        return \Plot::PROCESS_FULL;
    }

    public function plot(array $csvPaths): void
    {
        $this->cleanCurrentDir();
        $cutData = self::groupCSVFiles($csvPaths);
        self::writeCsv($cutData);
    }

    // ========================================================================
    public static function groupCSVFiles(array $csvFiles): array
    {
        $queries = \array_unique(\array_map(fn ($p) => \basename($p, '.csv'), $csvFiles));
        $dirs = \array_unique(\array_map(fn ($p) => \dirname($p), $csvFiles));
        \natcasesort($queries);

        $gdirs = [];

        foreach ($dirs as $dpath) {
            $d = \basename($dpath);
            $delements = \Help\Plotter::extractDirNameElements($d);
            unset($delements['partition']);
            unset($delements['full_group']);
            unset($delements['full_partition']);
            $dirGroup = \Help\Plotter::encodeDirNameElements($delements, '[%s]');

            foreach ($queries as $query)
                $ret[$query][$dirGroup][] = "$dpath/$query.csv";
        }
        return $ret;
    }

    // ========================================================================
    private const partitionsStatsGroups = [
        'answers',
        'queries'
    ];

    /**
     * Clean measures [$kgoup][$kmeasure][] that equals to 0
     */
    private static function cleanPartitionsStats(array $stats, string $kgroup, string $kmeasure): array
    {
        $drop = [];

        foreach ($stats[$kgroup][$kmeasure] as $k => $v) {

            if ((int) $v == 0)
                $drop[] = $k;
        }
        $ret = [];

        foreach ($stats as $kA => $itemsA)
            $ret[$kA] = \Help\Arrays::dropColumn($itemsA, ...$drop);

        return $ret;
    }

    public static function extractPartitionsStats(array $data, array $statsGroups = [])
    {
        if (empty($statsGroups))
            $statsGroups = self::partitionsStatsGroups;

        $partitionsName = [];

        $keys = \array_keys($data);

        foreach ($keys as $k) {

            if (! \preg_match("#^(.+)/#", $k, $matches))
                continue;

            $keysA[] = $matches[1];
        }
        $keysA = \array_unique($keysA);
        sort($keysA);

        $stats = [];

        foreach ($keysA as $kA) {
            $elements = \Help\Plotter::extractDirNameElements($kA);
            $partitionsName[] = $elements['partition'];
            $stats['partitions']['total'][] = 1;

            foreach ($statsGroups as $kB) {

                foreach ($data["$kA/$kB"] as $k => $v) {
                    $stats[$kB][$k][] = $v;
                }
            }
        }
        $stats['partition']['name'] = $partitionsName;
        $stats = [
            'partitions' => $stats,
            'partitions.used' => self::cleanPartitionsStats($stats, 'queries', 'total'),
            'partitions.hasAnswer' => self::cleanPartitionsStats($stats, 'answers', 'total')
        ];
        $ret = [];

        foreach ($stats as $type => $subStats) {

            foreach ($subStats as $kA => $itemsA) {

                foreach (\array_keys($itemsA) as $kB) {
                    $ret[$type]["$kA.$kB"] = \array_sum($itemsA[$kB]);
                    $ret[$type]["each.$kA.$kB"] = \implode(',', $itemsA[$kB]);
                }
            }
        }
        // Clean unusefull stats
        $ret = \Help\Arrays::dropColumn($ret, ...[
            'each.partitions.total',
            'partition.name'
        ]);
        $ret = \Help\Arrays::renameColumn($ret, 'partitions.total', 'total', 'each.partition.name', 'names');
        return $ret;
    }

    protected static function prepareData(array $data, string $partitionDataGroup, array &$partitionsData, array &$ret): void
    {}

    private static function prepareMeasures(array $colls): array
    {
        $nbColls = \count($colls);

        $staticParts = [
            'bench'
        ];
        $ret = null;
        $ret['collections']['nb'] = $nbColls;
        $partitionsData = [];

        foreach ($colls as $csvPath) {
            $data = \is_file($csvPath) ? \CSVReader::read($csvPath) : [];
            $data = \Benchmark::normalizeDataMeasures($data);

            $elements = \Help\Plotter::extractDirNameElements(\basename(\dirname($csvPath)));
            $partitionDataGroup = \Help\Plotter::encodeDirNameElements(\Help\Arrays::subSelect($elements, [
                'group',
                'rules',
                'partitioning',
                'partition',
                'qualifiers'
            ]), '');
            foreach ($data as $k => $items) {

                if (\Benchmark::isArrayMeasure($items)) {
                    $partitionsData["$partitionDataGroup/measures"][$k] = $items;
                } else {
                    $partitionsData["$partitionDataGroup/$k"] = $items;
                }

                // Do not sum static items
                if (\in_array($k, $staticParts)) {

                    if (! isset($ret[$k]))
                        $ret[$k] = $items;
                    elseif ($ret[$k] != $items)
                        fprintf(STDERR, "Not the same $k value:\nHave:\n" . print_r($ret[$k], true) . "Set:\n" . print_r($items, true));
                } else {

                    foreach ($items as $ki => $item) {

                        if (\Benchmark::isArrayMeasure($item)) {

                            if (! isset($ret[$k][$ki]))
                                $ret[$k][$ki] = $item;
                            else
                                $ret[$k][$ki] = \Benchmark::sumArrayMeasures($ret[$k][$ki], $item);
                        } elseif (! isset($ret[$k][$ki]))
                            $ret[$k][$ki] = (int) $item;
                        else
                            $ret[$k][$ki] += (int) $item;
                    }
                }
            }
        }
        $partitionsStats = self::extractPartitionsStats($partitionsData);
        $ret += $partitionsStats;

        uksort($partitionsData, 'strnatcasecmp');
        $ret += $partitionsData;
        return $ret;
    }

    private static function writeCsv(array $cutData): array
    {
        $newCsvFiles = [];

        foreach ($cutData as $query => $groups) {

            foreach ($groups as $group => $colls) {
                $prepareMeasures = self::prepareMeasures($colls);
                $prepareMeasures = \Benchmark::toStringDataMeasures($prepareMeasures);
                $basePath = $group;

                if (! \is_dir($basePath))
                    \mkdir($basePath);

                $csvFile = "$basePath/$query.csv";
                \CSVReader::write($csvFile, $prepareMeasures);
                $newCsvFiles[] = $csvFile;
            }
        }
        return $newCsvFiles;
    }
}
