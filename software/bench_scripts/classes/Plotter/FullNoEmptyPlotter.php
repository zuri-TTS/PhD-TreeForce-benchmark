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

    public static function defaultPlotConfig(): array
    {
        return [
            'plot.yrange.min' => 0,
            'plot.yrange.max' => 100,
            'plot.yrange.step' => 50,
            'plot.y.step' => 50,
            'plot.format.y' => "%.0f%%",
            'plot.ylabel.format' => "%.0f%%",
            'measure.div' => 1,
            'toPlot' => [
                'queries:%empty.nodeleted',
                'queries.ref:empty',
                'queries:total',
                'queries:noempty',
                'queries:noempty/total'
            ],
            'plot.measures' => [
                [
                    'queries:%empty.nodeleted' => 'ratio'
                ]
            ]
        ];
        ;
    }

    public function plot(array $csvPaths): void
    {
        $cutData = $this->groupCsvFiles($csvPaths);
        $this->cleanCurrentDir();
        $this->writeCsv($cutData);

        $argv = [
            '',
            'types=full',
            \getcwd()
        ];
        \include_script(getPHPScriptsBasePath() . '/plot.php', $argv);
    }

    // ========================================================================
    private const delements_selection = [
        'group',
        'rules',
        'partitioning',
        'qualifiers',
        'summary',
        'parallel',
        'filter_prefix'
    ];

    private const ref_delements_selection = [
        'group',
        'rules',
        'partitioning',
        'qualifiers'
    ];

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

        $keyGroup = \Help\Plotter::encodeDirNameElements(\Help\Arrays::subSelect($delements, self::ref_delements_selection));

        if (isset($this->refGroup[$keyGroup]))
            throw new \Exception("A reference group is already present: " . json_encode($this->refGroup[$keyGroup]));

        foreach ($queries as $query)
            $refGroup[$query] = "$dirPath/$query.csv";

        $this->refGroup[$keyGroup] = $refGroup;
    }

    private function groupCsvFiles(array $csvFiles): array
    {
        $queries = \array_unique(\array_map(fn ($p) => \basename($p, '.csv'), $csvFiles));
        $dirs = \array_unique(\array_map(fn ($p) => \dirname($p), $csvFiles));
        \natcasesort($queries);

        $gdirs = [];
        $refGroup = null;

        foreach ($dirs as $dpath) {
            $d = \basename($dpath);
            $delements = \Help\Plotter::extractDirNameElements($d);
            $sdelements = \Help\Arrays::subSelect($delements, self::delements_selection);

            $keyGroup = \Help\Plotter::encodeDirNameElements($sdelements);
            $this->makeRefGroup($dpath, $sdelements, $queries);

            foreach ($queries as $query) {
                $fname = "$dpath/$query";

                $infosPath = "{$fname}_config.txt";

                if (is_file($infosPath)) {
                    $infos = \Help\Plotter::readJavaProperties(\fopen($infosPath, 'r'));
                    $filter = $infos['querying.filter'];

                    if (isset($ret[$query][$keyGroup][$filter]))
                        error("More than one file for $query/$keyGroup/$filter:\n", $ret[$query][$keyGroup][$filter], "\n", "$fname.csv");
                    else
                        $ret[$query][$keyGroup][$filter] = "$fname.csv";
                }
            }
        }

        if (empty($this->refGroup))
            throw new \Exception("Error, no reference group found");

        return $ret;
    }

    // ========================================================================
    private function prepareMeasures(array $delements, string $query, array $csvFiles): array
    {
        $normal = \CSVReader::read($csvFiles['']);
        $noempty = \CSVReader::read($csvFiles['noempty']);

        $keyGroup = \Help\Plotter::encodeDirNameElements(\Help\Arrays::subSelect($delements, self::ref_delements_selection));
        $ref = \CSVReader::read($this->refGroup[$keyGroup][$query]);

        $noEmptyQueries = $noempty['queries']['total'];

        $totalQueries = $normal['queries']['total'];
        $emptyQueries = $totalQueries - $noEmptyQueries;

        $refTotalQueries = $ref['queries']['total'];
        $refEmptyQueries = $refTotalQueries - $noEmptyQueries;

        $nbDeletedEmptyQueries = $refEmptyQueries - $emptyQueries;

        if ($refEmptyQueries == 0)
            $noemptyDeleted = 100;
        else
            $noemptyDeleted = ((float) $nbDeletedEmptyQueries / $refEmptyQueries) * 100;

        $ret = [
            'queries' => [
                'total' => $totalQueries,
                'noempty' => $noEmptyQueries,
                'noempty/total' => ((float) $noEmptyQueries / $totalQueries) * 100,
                'empty' => $emptyQueries,
                'empty.deleted' => $nbDeletedEmptyQueries,
                '%empty.deleted' => $noemptyDeleted,
                '%empty.nodeleted' => ((float) $emptyQueries / $refEmptyQueries) * 100
            ],
            'queries.ref' => [
                'total' => $refTotalQueries,
                'empty' => $refEmptyQueries,
                'noempty' => $refTotalQueries - $refEmptyQueries
            ],
            'answers' => [
                'total' => $normal['answers']['total'],
                'noempty' => $noempty['answers']['total']
            ]
        ];
        return $ret;
    }

    private function writeCsv(array $cutData)
    {
        foreach ($cutData as $query => $groups) {

            foreach ($groups as $keyGroup => $csvFiles) {
                $nbFiles = count($csvFiles);

                if ($nbFiles == 1)
                    continue;
                elseif ($nbFiles > 2) {
                    // Normally impossible
                    \error("Warning! $nbFiles csv files possible for query $query:\n", implode("\n", $csvFiles), "\n");
                    continue;
                }
                $delements = \Help\Plotter::extractDirNameElements($keyGroup);
                $prepareMeasures = $this->prepareMeasures($delements, $query, $csvFiles);

                $basePath = $keyGroup;

                if (! \is_dir($basePath))
                    \mkdir($basePath);

                $csvFile = "$basePath/$query.csv";
                \CSVReader::write($csvFile, $prepareMeasures);
            }
        }
    }

    public static function makeXTic_clean(bool $showNbEmptyQueries = true, bool $showNbAnswers = false, bool $showRules = false)
    {
        return function ($testData, $query, $partitionsData) use ($showNbEmptyQueries, $showNbAnswers, $showRules) {
            foreach ($testData as $dirName => $data)
                break;

            $firstPart = \Plotter\AbstractFullStrategy::makeXTic_clean('', false, true)($testData, $query, $partitionsData);
            $csvData = \CSVReader::read("../$dirName/$query.csv");

            if ($showNbEmptyQueries) {
                $nbDeletedEmpty = $csvData['queries']['empty.deleted'];
                $refNbEmpty = $csvData['queries.ref']['empty'];

                if ($refNbEmpty != 0) {

                    if ($nbDeletedEmpty != $refNbEmpty)
                        $displayInfos = ": $nbDeletedEmpty/$refNbEmpty";
                    else
                        $displayInfos = ": $nbDeletedEmpty";
                } else
                    $displayInfos = "";

                return "$firstPart$displayInfos";
            } else
                return $firstPart;
        };
    }
}
