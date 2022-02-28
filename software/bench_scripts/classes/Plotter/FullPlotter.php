<?php
namespace Plotter;

final class FullPlotter implements IPlotter
{

    private const template = __DIR__ . '/templates/full.plot.php';

    private \Plot $plot;

    private bool $xtics_pretty = true;

    private bool $xtics_infos = true;

    private bool $xtics_infos_answers_nb = true;

    public function __construct(\Plot $plot)
    {
        $this->plot = $plot;
    }

    public function getId(): string
    {
        return 'group_query';
    }

    public function getProcessType(): string
    {
        return \Plot::PROCESS_FULL;
    }

    private array $toPlot = [
        'rewriting.rules.apply' => 'r',
        'rewriting.total' => 'r',
        'stats.db.time' => 'r'
    ];

    private array $cutData;

    private array $dirs;

    private array $csvPaths;

    private array $queries;

    public function getQueries(): array
    {
        return $this->queries;
    }

    public function getCutData(): array
    {
        return $this->cutData;
    }

    public function toPlot(): array
    {
        return $this->toPlot;
    }

    public function getMeasuresToPlot(): array
    {
        $ret = [];

        foreach ($this->toPlot as $what => $times) {
            foreach ((array) $times as $t)
                $ret[] = "$what.$t";
        }
        return $ret;
    }

    public function plot(array $csvPaths): void
    {
        $this->csvPaths = $csvPaths;
        $this->cleanCurrentDir();
        $this->queries = \array_unique(\array_map(fn ($p) => \basename($p, '.csv'), $csvPaths));

        $this->dirs = \array_unique(\array_map(fn ($p) => \dirname($p), $csvPaths));
        \natsort($this->dirs);

        $this->cutData = self::cutData_group_query($csvPaths);
        $this->writeDat($this->cutData);

        $contents = \get_include_contents(self::template, [
            'PLOT' => $this->plot,
            'PLOTTER' => $this
        ]);
        $plotFileName = 'all_time.plot';
        \file_put_contents($plotFileName, $contents);

        $outFileName = 'all_time.png';
        $cmd = "gnuplot '$plotFileName' > '$outFileName'";
        echo "plotting $outFileName\n";

        system($cmd);
    }

    private function cleanCurrentDir()
    {
        foreach ($g = \glob('*.dat') as $file)
            \unlink($file);
    }

    public function getCsvData(?string $csvPath = null): array
    {
        if (null === $csvPath)
            return \array_map(fn ($p) => \CSVReader::read($p), $this->csvPaths);

        return \CSVReader::read($csvPath);
    }

    // ========================================================================
    private function makeXTic(string $dirName, int $nbReformulations, int $nbAnswers)
    {
        if (\preg_match("#^\[.+\]\[(.+)\]#U", $dirName, $matches)) {

            if ($this->xtics_pretty) {
                if (\preg_match("#^\((\d+)\)#U", $matches[1], $smatches))
                    $ret = $smatches[1];
                else
                    $ret = $matches[1];
            } else
                $ret = $matches[0];
        } else
            $ret = $dirName;

        $infos = '';

        if ($this->xtics_infos) {

            if ($nbReformulations != $ret)
                $infos .= "$nbReformulations";

            if ($this->xtics_infos_answers_nb)
                $infos .= ",$nbAnswers";

            $ret = "$ret($infos)";
        }
        return \Plot::gnuplotSpecialChars($ret);
    }

    private static function cutData_group_query(array $csvFiles): array
    {
        $queries = \array_unique(\array_map(fn ($p) => \basename($p, '.csv'), $csvFiles));
        $dirs = \array_unique(\array_map(fn ($p) => \dirname($p), $csvFiles));
        $groups = \array_map(function ($p) {
            \preg_match("#^\[(.+)\]#U", \basename($p), $matches);
            \preg_match("#\[(simplified.*)\]#U", \basename($p), $simplified);
            return [
                $matches[1],
                ($simplified[1] ?? '')
            ];
        }, $dirs);
        $groups = \array_unique($groups, SORT_REGULAR);
        \usort($groups, fn ($a, $b) => strnatcasecmp($a[0], $b[0]));

        foreach ($groups as $group) {
            $g = $group[0];
            $s = $group[1];
            $group = $g;
            $pattern = "*\[$g\]*";

            if ($s) {
                $pattern .= "[$s\]*";
                $group .= "[$s]";
            }
            $gdirs = \array_filter($dirs, fn ($d) => \fnmatch($pattern, $d));

            if ($s === '')
                $gdirs = \array_filter($gdirs, fn ($d) => ! \fnmatch("*\[simplified*\]*", $d));

            foreach ($queries as $query) {
                $dd = \array_map(fn ($p) => "$p/$query.csv", $gdirs);
                \natcasesort($dd);
                $ret["{$group}_$query"] = \array_values($dd);
            }
        }

        \uksort($ret, function ($a, $b) {
            $ga = FullPlotter::nbFromGroupName($a);
            $gb = FullPlotter::nbFromGroupName($b);
            $diff = $ga - $gb;

            if ($diff)
                return $diff;

            return \strnatcasecmp($a, $b);
        });
        return $ret;
    }

    private const factors = [
        'K' => 10 ** 3,
        'M' => 10 ** 6,
        'G' => 10 ** 9
    ];

    private static function nbFromGroupName(string $groupName)
    {
        if (\preg_match('#^(\d+)([KMG])#', $groupName, $matches))
            return (int) $matches[1] * (self::factors[$matches[2]] ?? 0);
        return 0;
    }

    private function echoDat(array $csvFiles)
    {
        echo "test ";

        foreach ($this->toPlot as $what => $times) {
            foreach ((array) $times as $t)
                echo "$what.$t ";
        }
        echo "\n";

        foreach ($csvFiles as $csvPath) {
            $dirName = \basename(\dirname($csvPath));
            $data = \is_file($csvPath) ? \CSVReader::read($csvPath) : [];
            $nbReformulations = $data['queries']['total'];
            $nbAnswers = $data['answers']['total'];

            $xtic = $this->makeXTic($dirName, $nbReformulations, $nbAnswers);
            echo "\"$xtic\" ";

            foreach ($this->toPlot as $what => $times) {

                if (isset($data[$what])) {

                    foreach ((array) $times as $t)
                        $dat = $data[$what][$t] ?? '0';
                } else
                    $dat = '0';

                echo "$dat ";
            }
            echo "\n";
        }
    }

    private function writeDat(array $cutData)
    {
        foreach ($cutData as $file => $csvFiles) {
            $file = "$file.dat";
            echo "Writing $file\n";
            $content = \get_ob(fn () => $this->echoDat($csvFiles));

            \file_put_contents($file, $content);
        }
    }
}
