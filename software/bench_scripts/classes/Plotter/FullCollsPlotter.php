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
        $this->csvPaths = $csvPaths;
        $this->cleanCurrentDir();
        $this->queries = \array_unique(\array_map(fn ($p) => \basename($p, '.csv'), $csvPaths));

        $this->dirs = \array_unique(\array_map(fn ($p) => \dirname($p), $csvPaths));
        \natsort($this->dirs);

        $this->cutData = self::cutData_colls_query($csvPaths);
        $this->writeCsv($this->cutData);

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
            \preg_match("#^\[(.+)\]\[(.+)\]\[(.*)\]#U", $dirName, $matches);
            list ($group, $coll) = \explode('.', $matches[1]);
            $rules = $matches[2];
            $qualifs = $matches[3];

            // Remove cmd & date
            $dirCleaned = \preg_replace("#\{.+\}\[.+\]#U", "", $dirName, 1);
            return [
                $group,
                $coll,
                $rules,
                $qualifs,
                $p,
                \preg_replace("#^\[$group.$coll\]#U", "[$group]", $dirCleaned, 1)
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
            list ($g, $c, $r, $q, $p, $d) = $group;
            $pattern = "*\[$g\]\[$q\]*";

            $gdirs = \array_filter($dirs, fn ($d) => \fnmatch($pattern, $d));

            foreach ($queries as $query) {
                $dd = \array_map(fn ($p) => "$p/$query.csv", $gdirs);
                \natcasesort($dd);
                $ret[$query][$d][$c] = "$p/$query.csv";
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
