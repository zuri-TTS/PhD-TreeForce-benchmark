<?php
namespace Plotter;

final class FullPlotter extends AbstractFullPlotter
{

    private const template = __DIR__ . '/templates/full.plot.php';

    private \Plot $plot;

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
        'rewritings.generation' => 'r',
        'stats.db.time' => 'r',
        'threads.time' => 'r'
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
        $this->writeCsv($this->cutData);

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

    public function getCsvData(?string $csvPath = null): array
    {
        if (null === $csvPath)
            return \array_map(fn ($p) => \CSVReader::read($p), $this->csvPaths);

        return \CSVReader::read($csvPath);
    }

    // ========================================================================
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
            $nbReformulations = $data['queries']['total'] ?? - 1;
            $nbAnswers = $data['answers']['total'] ?? - 1;

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

    private function echoCsv(string $name, array $csvFiles)
    {
        foreach ($csvFiles as $csvPath) {
            $dirName = \basename(\dirname($csvPath));
            $data = \is_file($csvPath) ? \CSVReader::read($csvPath) : [];
            $nbReformulations = $data['queries']['total'] ?? - 1;
            $nbAnswers = $data['answers']['total'] ?? - 1;

            $xtic = $this->makeXTic($dirName, $nbReformulations, $nbAnswers);
            echo "\"$name $xtic\"";

            foreach ($this->toPlot as $what => $times) {

                if (isset($data[$what])) {

                    foreach ((array) $times as $t)
                        $dat = $data[$what][$t] ?? '0';
                } else
                    $dat = '0';

                echo ",$dat";
            }
            echo "\n";
        }
    }

    private function writeCsv(array $cutData)
    {
        $file = "table.csv";
        echo "Writing $file\n";
        $fp = \fopen($file, "w");

        $head = "\"rules[summary,2native](nbRefs,nbAns)\"";

        foreach ($this->toPlot as $what => $times) {

            foreach ((array) $times as $t)
                $head .= ",$what.$t";
        }
        $head .= "\n";
        \fwrite($fp, $head);

        foreach ($cutData as $file => $csvFiles) {
            $content = \get_ob(fn () => $this->echoCsv($file, $csvFiles));
            \fwrite($fp, $content);
        }
        \fclose($fp);
    }
}
