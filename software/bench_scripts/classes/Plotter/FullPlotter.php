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
    private array $csvPaths;

    private array $csvGroups;

    private array $groupsInfos;

    private array $csvData;

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

    public function getCsvGroups(): array
    {
        return $this->csvGroups;
    }

    public function getNbGroups(): int
    {
        return \count($this->csvGroups);
    }

    public function getCsvData(?string $csvPath = null): array
    {
        if (null === $csvPath)
            return \array_map(fn ($p) => \CSVReader::read($p), $this->csvPaths);

        return \CSVReader::read($csvPath);
    }

    public function plot_getConfig(): array
    {
        return $this->plotConfig;
    }

    public function plot(array $csvPaths): void
    {
        $this->csvPaths = $csvPaths;
        $this->cleanCurrentDir();
        $queries = \array_unique(\array_map(fn ($p) => \basename($p, '.csv'), $csvPaths));

        $plotConfig = $this->strategy->plot_getConfig();
        $plotGroups = $plotConfig['plot.groups'] ?? null;
        $this->plotConfig = $plotConfig;

        if (empty($plotGroups))
            $plotGroups = [
                null
            ];

        $nbGroups = \count($plotGroups);

        foreach ($plotGroups as $groupName => $group) {

            if (empty($group))
                $group = [];

            $groupQueries = $group['queries'] ?? $queries;
            $groupConfig = $group['config'] ?? [];

            $processedGroups = [];
            $this->queries = $groupQueries;
            $this->plotConfig = \array_merge($plotConfig, $groupConfig);
            $this->csvGroups = [];

            // Write files csv & dat
            {
                $g = $this->plotConfig['@group'] ?? '';

                if (! isset($processedGroups[$g])) {
                    $this->plotConfig['@group'] = $g;
                    $processedGroups[$g] = true;
                    $csvGroups = $this->strategy->groupCSVFiles($csvPaths);
                    $this->writeDat($csvGroups);
                    $this->writeCsv($csvGroups);
                }
            }

            foreach ($csvGroups as $csvGroup => $files) {
                $files = \array_filter($files, fn ($p) => in_array(\basename($p, '.csv'), $groupQueries));

                if (empty($files))
                    continue;

                $this->csvGroups[$csvGroup] = $files;
                $this->groupsInfos[$csvGroup]['nb'] = \count($files);
            }

            if (empty($this->csvGroups))
                continue;

            $contents = \get_include_contents($this->template, [
                'PLOT' => $this->plot,
                'PLOTTER' => $this
            ]);
            $fileName = "all_time";

            if ($nbGroups > 1)
                $fileName .= $groupName;

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
    private function echoDat(array $csvFiles)
    {
        $header = $this->strategy->getDataHeader();
        $header = \array_map('Help\Plotter::encodeDataValue', $header);
        echo implode(' ', $header), "\n";
        $data = [];

        foreach ($csvFiles as $csvPath) {
            $dirName = \basename(\dirname($csvPath));
            $dataLine = $this->strategy->getDataLine($csvPath);
            $this->csvData[$csvPath] = $dataLine;
            $dataLine = \array_map('Help\Plotter::encodeDataValue', $dataLine);
            $data[] = $dataLine;
        }
        $this->strategy->sortDataLines($data);

        foreach ($data as $dataLine)
            echo implode(' ', $dataLine), "\n";
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
            $dataLine = $this->csvData[$csvPath];
            $dataLine[0] = "$name/{$dataLine[0]}";
            $dataLine = \array_map('Help\Plotter::encodeDataValue', $dataLine);
            echo implode(',', $dataLine), "\n";
        }
    }

    private function writeCsv(array $cutData)
    {
        $group = $this->plotConfig['@group'];
        $file = "table$group.csv";
        echo "Writing $file\n";
        $fp = \fopen($file, "w");

        $header = $this->strategy->getDataHeader();
        $header = \array_map('Help\Plotter::encodeDataValue', $header);
        fwrite($fp, implode(',', $header) . "\n");

        foreach ($cutData as $file => $csvFiles) {
            $content = \get_ob(fn () => $this->echoCsv($file, $csvFiles));
            \fwrite($fp, $content);
        }
        \fclose($fp);
    }
}
