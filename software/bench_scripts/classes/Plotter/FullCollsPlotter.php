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
            $rules = $pelements['rules'];
            $qualifiers = $pelements['qualifiers'];

            $gp = $group;

            if (! empty($partitioning))
                $gp .= ".$partitioning";

            // Remove cmd & date
            $dirCleaned = \preg_replace("#\{.+\}\[.+\]#U", "%s", $dirName, 1);
            return [
                $group,
                $partitioning,
                $rules,
                $qualifiers,
                // $p,
                \preg_replace("#^\[$initialGroup\]#U", "[$gp%s]", $dirCleaned, 1)
            ];
        }, $dirs);
        $groups = \array_unique($groups, SORT_REGULAR);
        \usort($groups, function ($a, $b) {
            $ret = \strnatcasecmp($a[0], $b[0]);
            if ($ret)
                return $ret;
            return \strnatcasecmp($a[1], $b[1]);
        });
        \natcasesort($queries);

        foreach ($groups as $group) {
            list ($g, $p, $r, $q, $dpattern) = $group;
            $d = \sprintf($dpattern, '', '');
            $regex = \preg_quote($dpattern);
            $regex = \sprintf($regex, "(\..+)?", "\{.+\}\[.+\]");

            $gdirs = \array_filter($dirs, fn ($dpattern) => \preg_match("#$regex#U", $dpattern));
            \natcasesort($gdirs);

            foreach ($queries as $query) {
                $dd = \array_map(fn ($p) => "$p/$query.csv", $gdirs);

                foreach ($gdirs as $dir) {
                    $ret[$query][$d][$dir] = "$dir/$query.csv";
                }
            }
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
