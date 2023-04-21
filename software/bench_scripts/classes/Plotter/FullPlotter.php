<?php
namespace Plotter;

final class FullPlotter extends AbstractFullPlotter
{

    private $template = __DIR__ . '/templates/full.plot.php';

    private \Plot $plot;

    private IFullPlotterStrategy $strategy;

    public function __construct(\Plot $plot, IFullPlotterStrategy $strategy)
    {
        $this->plot = $plot;
        $this->strategy = $strategy;
        $strategy->setPlotter($this);
    }

    public function setTemplate(string $name, string $dir = __DIR__ . '/templates'): self
    {
        $this->template = "$dir/$name";
        return $this;
    }

    public function getID(): string
    {
        return $this->strategy->getID();
    }

    public function getProcessType(): string
    {
        return \Plot::PROCESS_FULL;
    }

    // ========================================================================
    private array $tests;

    private array $testGroups;

    private array $groupsInfos;

    private array $testsData;

    private array $queries;

    private array $plotConfig;

    public function getStrategy()
    {
        return $this->strategy;
    }

    public function getQueries(): array
    {
        return $this->queries;
    }

    public function getGroupsInfos(): array
    {
        return $this->groupsInfos;
    }

    public function getTestGroups(): array
    {
        return $this->testGroups;
    }

    public function getTestData(string $test): array
    {
        // \wdPush("..");
        // $groupName = \dirname($test);
        // $query = \basename($test);
        // $ret = (new \Measures($groupName))->loadMeasuresOf($query);
        // \wdPop();
        return $this->plot->getData()[$test];
    }

    public function getNbGroups(): int
    {
        return \count($this->testGroups);
    }

    public function plot_getConfig(): array
    {
        return $this->plotConfig;
    }

    public function plot(array $tests): void
    {
        $this->tests = $tests;
        $queries = \array_unique(\array_map(fn ($p) => \basename($p), $tests));

        $plotConfig = $this->strategy->plot_getConfig();
        $plotGroups = $plotConfig['plot.groups'] ?? null;
        $this->plotConfig = $plotConfig;

        if (empty($plotGroups))
            $plotGroups = [
                null
            ];

        $nbGroups = \count($plotGroups);
        $processedGroups = [];

        foreach ($plotGroups as $plotGroupName => $group) {

            if (empty($group))
                $group = [];

            $groupQueries = $group['queries'] ?? $queries;
            $groupConfig = $group['config'] ?? [];

            if (is_callable($groupConfig))
                $groupConfig = $groupConfig($plotConfig);

            $this->plotConfig = \array_merge($plotConfig, $groupConfig);
            $groupsConfig = $this->plotConfig = \array_merge($this->plotConfig, $this->plotConfig['plot.groups.config'] ?? []);

            $this->queries = $groupQueries;
            $this->testGroups = [];

            // Write files csv & dat
            {
                // @group as possible parameter for groupTests() like in FullLineStrategy
                $g = $this->plotConfig['@group'] ?? '';

                if (! isset($processedGroups[$g])) {
                    $this->plotConfig['@group'] = $g;
                    $testGroups = $this->strategy->groupTests($tests);

                    foreach ($testGroups as $groupName => $tests) {
                        foreach ($tests as $test) {
                            $dataLine = $this->strategy->getDataLine($test, $this->plot->getData()[$test]);
                            $this->testsData[$test] = $dataLine;
                        }
                    }
                    $processedGroups[$g] = $testGroups;
                    $this->writeDat($testGroups);
                    $this->writeCsv($testGroups);
                    $this->writePhp($testGroups);
                } else
                    $testGroups = $processedGroups[$g];
            }

            foreach ($testGroups as $testGroup => $tests) {
                $tests = \array_filter($tests, fn ($p) => in_array(\basename($p), $groupQueries));

                if (empty($tests))
                    continue;

                $this->testGroups[$testGroup] = $tests;
                $this->groupsInfos[$testGroup]['nb'] = \count($tests);
            }

            if (empty($this->testGroups))
                continue;

            $contents = \get_include_contents($this->template, [
                'PLOT' => $this->plot,
                'PLOTTER' => $this
            ]);
            $fileName = "all_time";

            if ($nbGroups > 1)
                $fileName .= $plotGroupName;

            $plotFileName = "$fileName.plot";
            \file_put_contents($plotFileName, $contents);

            $extensions = $this->plotConfig['terminal'] ?? (array) 'png';

            if (! is_array($extensions))
                $extensions = [
                    $extensions
                ];

            foreach ($extensions as $extension) {
                $outFileName = "$fileName.$extension";
                $cmd = "gnuplot -e 'terminal=\"$extension\"' '$plotFileName' > '$outFileName'";
                echo "plotting $outFileName\n";

                \system($cmd);

                $here = \basename(\getcwd());
                $link = "../{$here}_$outFileName";

                if (! \is_file($link))
                    \symlink("$here/$outFileName", $link);
            }
        }
    }

    // ========================================================================
    private function writePhp(array $testGroups)
    {
        foreach ($testGroups as $groupName => $tests) {
            $file = "$groupName.php";

            if (\is_file($file))
                continue;

            echo "Writing $file\n";
            $data = [];

            foreach ($tests as $test) {
                $dirName = \dirname($test);
                $elements = \Help\Plotter::extractDirNameElements($dirName);
                $data[] = [
                    'dataLine' => \array_combine( //
                    $this->strategy->getDataHeader(), //
                    $this->strategy->getDataLine($test, $this->plot->getData()[$test]) //
                    ),
                    'elements' => $elements['full_pattern']
                ];
            }
            \file_put_contents($file, "<?php return " . var_export($data, true) . ';');
        }
    }

    private function fwriteDat($fp, array $tests)
    {
        $header = $this->strategy->getDataHeader();
        $header = \array_map('Help\Plotter::encodeDataValue', $header);
        fwrite($fp, implode(' ', $header) . "\n");
        $data = [];

        foreach ($tests as $test)
            $data[] = \array_map('\Help\Plotter::encodeDataValue', $this->testsData[$test]);

        $this->strategy->sortDataLines($data);

        foreach ($data as $dataLine)
            fwrite($fp, implode(' ', $dataLine) . "\n");
    }

    private function writeDat(array $testsGroup)
    {
        foreach ($testsGroup as $groupName => $tests) {
            $file = "$groupName.dat";

            if (\is_file($file))
                continue;

            $fp = \fopen($file, "w");
            echo "Writing $file\n";
            $this->fwriteDat($fp, $tests);
            \fclose($fp);
        }
    }

    private function fwriteCsv($fp, string $name, array $csvFiles)
    {
        foreach ($csvFiles as $csvPath) {
            $dataLine = $this->testsData[$csvPath];
            $dataLine[0] = "$name/{$dataLine[0]}";
            $dataLine = \array_map('Help\Plotter::encodeDataValue', $dataLine);
            fwrite($fp, implode(',', $dataLine) . "\n");
        }
    }

    private function writeCsv(array $cutData)
    {
        $group = $this->plotConfig['@group'];
        $file = "table$group.csv";

        if (\is_file($file))
            return;

        echo "Writing $file\n";
        $fp = \fopen($file, "w");

        $header = $this->strategy->getDataHeader();
        $header = \array_map('Help\Plotter::encodeDataValue', $header);
        fwrite($fp, implode(',', $header) . "\n");

        foreach ($cutData as $file => $csvFiles) {
            $this->fwriteCsv($fp, $file, $csvFiles);
        }
        \fclose($fp);
    }
}
