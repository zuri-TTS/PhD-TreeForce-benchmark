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

    private array $csvData;

    private array $queries;

    public function getStrategy()
    {
        return $this->strategy;
    }

    public function getQueries(): array
    {
        return $this->queries;
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

    public function plot(array $csvPaths): void
    {
        $this->csvPaths = $csvPaths;
        $this->cleanCurrentDir();
        $this->queries = \array_unique(\array_map(fn ($p) => \basename($p, '.csv'), $csvPaths));

        $csvGroups = $this->strategy->groupCSVFiles($csvPaths);
        $this->writeDat($csvGroups);
        $this->writeCsv($csvGroups);
        $this->csvGroups = $csvGroups;

        $contents = \get_include_contents($this->template, [
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

    // ========================================================================
    private function echoDat(array $csvFiles)
    {
        $header = $this->strategy->getDataHeader();
        $header = \array_map('Help\Plotter::encodeDataValue', $header);
        echo implode(' ', $header), "\n";

        foreach ($csvFiles as $csvPath) {
            $dirName = \basename(\dirname($csvPath));
            $dataLine = $this->strategy->getDataLine($csvPath);
            $this->csvData[$csvPath] = $dataLine;
            $dataLine = \array_map('Help\Plotter::encodeDataValue', $dataLine);
            echo implode(' ', $dataLine), "\n";
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
            $dataLine = $this->csvData[$csvPath];
            $dataLine[0] = "$name/{$dataLine[0]}";
            $dataLine = \array_map('Help\Plotter::encodeDataValue', $dataLine);
            echo implode(',', $dataLine), "\n";
        }
    }

    private function writeCsv(array $cutData)
    {
        $file = "table.csv";
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
