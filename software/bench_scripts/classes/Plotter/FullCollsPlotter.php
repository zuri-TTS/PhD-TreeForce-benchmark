<?php
namespace Plotter;

final class FullCollsPlotter extends AbstractFullPlotter
{

    public function getId(): string
    {
        return 'colls_query';
    }

    public function getProcessType(): string
    {
        return \Plot::PROCESS_FULL;
    }

    public function plot(array $csvPaths): void
    {
        $this->cleanCurrentDir();
        $cutData = self::cutData_colls_query($csvPaths);
        $this->writeCsv($cutData);

        $argv = [
            '',
            'types=full',
            \getcwd()
        ];
        \include_script(getPHPScriptsBasePath() . '/plot.php', $argv);

        $link = "all_time.png";

        if (! \is_file($link))
            \symlink("full_group_query/$link", $link);
    }

    // ========================================================================
    private static function cutData_colls_query(array $csvFiles): array
    {
        $queries = \array_unique(\array_map(fn ($p) => \basename($p, '.csv'), $csvFiles));
        $dirs = \array_unique(\array_map(fn ($p) => \dirname($p), $csvFiles));
        $groups = \array_map(function ($p) {
            $dirName = \basename($p);
            $pelements = \Help\Plotter::extractDirNameElements($dirName);

            $initialGroup = $pelements['full_group'];
            $group = $pelements['group'];
            $partitioning = $pelements['partitioning'];

            $gp = $group;

            if (! empty($partitioning))
                $gp .= ".$partitioning";

            // Remove cmd & date
            return $pelements;
        }, $dirs);
        \natcasesort($queries);

        $selection = [
            'group',
            'partitioning',
            'qualifiers',
            'summary'
        ];
        $gdirs = [];

        foreach ($dirs as $dpath) {
            $d = \basename($dpath);
            $delements = \Help\Plotter::extractDirNameElements($d);
            $sdelements = \Help\Arrays::subSelect($delements, $selection);
            $g = \Help\Strings::append('.', $delements['group'], $delements['partitioning']);

            $summary = $delements['summary'];
            if (! empty($summary))
                $summary = "[summary-$summary]";

            $dirGroup = "[$g][{$delements['rules']}][{$delements['qualifiers']}]$summary";

            foreach ($queries as $query)
                $ret[$query][$dirGroup][] = "$dpath/$query.csv";
        }
        return $ret;
    }

    // ========================================================================
    private const avg = [
        'rewriting.total',
        'rewriting.rules.apply'
    ];

    private function prepareMeasures(array $colls): array
    {
        $nbColls = \count($colls);

        $nbRefs = 0;
        $nbAnswers = 0;
        $staticParts = [
            'bench'
        ];
        $ret = null;
        $ret['collections']['nb'] = $nbColls;

        foreach ($colls as $csvPath) {
            $data = \is_file($csvPath) ? \CSVReader::read($csvPath) : [];

            foreach ($data as $k => $items) {

                // Do not sum static items
                if (\in_array($k, $staticParts)) {

                    if (! isset($ret[$k]))
                        $ret[$k] = $items;
                    elseif ($ret[$k] != $items)
                        fprintf(STDERR, "Not the same $k value:\nHave:\n" . print_r($ret[$k], true) . "Set:\n" . print_r($items, true));
                } else {

                    foreach ($items as $ki => $item) {
                        if (! isset($ret[$k][$ki]))
                            $ret[$k][$ki] = (int) $item;
                        else
                            $ret[$k][$ki] += (int) $item;
                    }
                }
            }
        }

        foreach (self::avg as $meas) {

            foreach ($ret[$meas] as &$val)
                $val /= $nbColls;
        }
        return $ret;
    }

    private function writeCsv(array $cutData)
    {
        foreach ($cutData as $query => $groups) {

            foreach ($groups as $group => $colls) {
                $prepareMeasures = $this->prepareMeasures($colls);
                $basePath = $group;

                if (! \is_dir($basePath))
                    \mkdir($basePath);

                $csvFile = "$basePath/$query.csv";
                \CSVReader::write($csvFile, $prepareMeasures);
            }
        }
    }
}
