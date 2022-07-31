<?php
namespace Plotter;

final class FullNoEmptyPlotter extends AbstractFullPlotter
{

    public function getId(): string
    {
        return 'noempty';
    }

    public function getProcessType(): string
    {
        return \Plot::PROCESS_FULL;
    }

    protected function cleanCurrentDir(string $glob = '*.dat')
    {
        parent::cleanCurrentDir($glob);

        foreach (\scandirNoPoints('.') as $file) {
            if (\is_dir($file))
                rrmdir($file);
        }
    }

    public static function defaultPlotConfig()
    {
        return [
            'plot.yrange.min' => 0,
            'plot.yrange.max' => 100,
            'plot.yrange.step' => 50,
            'plot.y.step' => 50,
            'plot.format.y' => "%.0f%%",
            'plot.ylabel.format' => "%.0f%%",
            'plot.measures' => [
                'total/noempty' => ''
            ],
            'measure.div' => 1,
            'toPlot' => [
                'queries' => 'noempty/deleted'
            ],
            'plot.measures' => [
                [
                    'queries' => 'ratio'
                ]
            ]
        ];
        ;
    }

    public function plot(array $csvPaths): void
    {
        $cutData = $this->groupCsvFiles($csvPaths);
        $this->writeCsv($cutData);

        $argv = [
            '',
            'types=full',
            \getcwd()
        ];
        \include_script(getPHPScriptsBasePath() . '/plot.php', $argv);
    }

    // ========================================================================
    private $refGroup = null;

    private function makeRefGroup(string $dirPath, array $delements, array $queries): void
    {
        if ( //
        ! empty($delements['summary']) || //
        ! empty($delements['partitioning']) || //
        $delements['parallel'])
            return;

        $query = $queries[0];
        $infosPath = "{$dirPath}/{$query}_config.txt";

        if (empty($infosPath))
            return;

        $infos = \Help\Plotter::readJavaProperties(\fopen($infosPath, 'r'));

        if (! empty($infos['querying.filter']))
            return;

        foreach ($queries as $query)
            $refGroup[$query] = "$dirPath/$query.csv";

        if (null !== $this->refGroup)
            throw new \Exception("A reference group is already present: " . json_encode($this->refGroup));

        $this->refGroup = $refGroup;
    }

    private function groupCsvFiles(array $csvFiles): array
    {
        $queries = \array_unique(\array_map(fn ($p) => \basename($p, '.csv'), $csvFiles));
        $dirs = \array_unique(\array_map(fn ($p) => \dirname($p), $csvFiles));
        \natcasesort($queries);

        $selection = [
            'group',
            'partitioning',
            'qualifiers',
            'summary',
            'parallel',
            'filter_prefix'
        ];
        $gdirs = [];
        $refGroup = null;

        foreach ($dirs as $dpath) {
            $d = \basename($dpath);
            $delements = \Help\Plotter::extractDirNameElements($d);
            $sdelements = \Help\Arrays::subSelect($delements, $selection);
            $this->makeRefGroup($dpath, $delements, $queries);

            foreach ($queries as $query) {
                $fname = "$dpath/$query";

                $infosPath = "{$fname}_config.txt";

                if (is_file($infosPath)) {
                    $infos = \Help\Plotter::readJavaProperties(\fopen($infosPath, 'r'));
                    $filter = $infos['querying.filter'];
                    $group = \Help\Plotter::encodeDirNameElements($delements);
                    $group = \sprintf($group, '{}[]');

                    if (isset($ret[$query][$group][$filter]))
                        error("More than one file for $query/$group/$filter:\n", $ret[$query][$group][$filter], "\n", "$fname.csv");
                    else
                        $ret[$query][$group][$filter] = "$fname.csv";
                }
            }
        }

        if (empty($this->refGroup))
            throw new \Exception("Error, no reference group found");

        return $ret;
    }

    // ========================================================================
    private function prepareMeasures(string $query, array $csvFiles): array
    {
        $normal = \CSVReader::read($csvFiles['']);
        $noempty = \CSVReader::read($csvFiles['noempty']);
        $ref = \CSVReader::read($this->refGroup[$query]);

        $noEmptyQueries = $noempty['queries']['total'];

        $totalQueries = $normal['queries']['total'];
        $emptyQueries = $totalQueries - $noEmptyQueries;

        $refTotalQueries = $ref['queries']['total'];
        $refEmptyQueries = $refTotalQueries - $noEmptyQueries;

        $nbDeletedEmptyQueries = $refEmptyQueries - $emptyQueries;

        $ret['queries'] = [
            'total' => $totalQueries,
            'noempty' => $noEmptyQueries,
            'noempty/total' => ((float) $noEmptyQueries / $totalQueries) * 100,
            'noempty/deleted' => ((float) $nbDeletedEmptyQueries / $refEmptyQueries) * 100
        ];
        $ret['answers'] = [
            'total' => $normal['answers']['total'],
            'noempty' => $noempty['answers']['total']
        ];
        return $ret;
    }

    private function writeCsv(array $cutData)
    {
        foreach ($cutData as $query => $groups) {

            foreach ($groups as $group => $csvFiles) {
                $nbFiles = count($csvFiles);

                if ($nbFiles == 1)
                    continue;
                elseif ($nbFiles > 2) {
                    // Normally impossible
                    \error("Warning! $nbFiles csv files possible for query $query:\n", implode("\n", $csvFiles), "\n");
                    continue;
                }
                $prepareMeasures = $this->prepareMeasures($query, $csvFiles);
                $basePath = $group;

                if (! \is_dir($basePath))
                    \mkdir($basePath);

                $csvFile = "$basePath/$query.csv";
                \CSVReader::write($csvFile, $prepareMeasures);
            }
        }
    }
}
