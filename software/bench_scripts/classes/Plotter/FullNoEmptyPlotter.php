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

    protected function cleanCurrentDir()
    {
        parent::cleanCurrentDir();

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
            'plot.format.y' => "%g%%",
            'plot.measures' => [
                'total/noempty' => 'temps'
            ],
            'toPlot' => [
                'queries' => 'noempty/total'
            ],
            'measure.div' => 1,
            'toPlot' => [
                'queries' => 'noempty/total'
            ],
            'plot.measures' => [
                [
                    'queries' => 'ratio'
                ],
            ]
        ];
        ;
    }

    public function plot(array $csvPaths): void
    {
        $cutData = self::groupCsvFiles($csvPaths);
        $this->writeCsv($cutData);

        $argv = [
            '',
            'types=full',
            \getcwd()
        ];
        \include_script(getPHPScriptsBasePath() . '/plot.php', $argv);
    }

    // ========================================================================
    private static function groupCsvFiles(array $csvFiles): array
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

        foreach ($dirs as $dpath) {
            $d = \basename($dpath);
            $delements = \Help\Plotter::extractDirNameElements($d);
            $sdelements = \Help\Arrays::subSelect($delements, $selection);

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
        return $ret;
    }

    // ========================================================================
    private function prepareMeasures(array $csvFiles): array
    {
        $normal = \CSVReader::read($csvFiles['']);
        $noempty = \CSVReader::read($csvFiles['noempty']);
        $ret['queries'] = [
            'total' => $t = $normal['queries']['total'],
            'noempty' => $n = $noempty['queries']['total'],
            'noempty/total' => (float) ($n / $t) * 100
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
                $prepareMeasures = $this->prepareMeasures($csvFiles);
                $basePath = $group;

                if (! \is_dir($basePath))
                    \mkdir($basePath);

                $csvFile = "$basePath/$query.csv";
                \CSVReader::write($csvFile, $prepareMeasures);
            }
        }
    }
}
