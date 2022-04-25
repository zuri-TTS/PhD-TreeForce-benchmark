<?php

final class Plot
{

    public const PROCESS_FULL = 'full';

    public const PROCESS_GROUP = 'group';

    public const PROCESS_EACH = 'each';

    private array $data;

    private array $csvGroupByBasePath;

    private string $workingDir;

    private array $graphics;

    function __construct(string $workingDir, array $csvPaths)
    {
        $this->workingDir = \realpath($workingDir);

        foreach ($csvPaths as $csvPath) {
            $groupByBase[\dirname($csvPath)][] = \basename($csvPath, '.csv');
        }
        foreach ($groupByBase as &$group)
            \natsort($group);

        $this->csvGroupByBasePath = $groupByBase;
    }

    public function getData(): array
    {
        return $this->data;
    }

    // ========================================================================
    private function nbMeasures(array $data)
    {
        return count(\array_filter($data, "self::isTimeMeasure"));
    }

    private function maxRealTime(array $data)
    {
        $r = \array_column(\array_filter($data, "self::isTimeMeasure"), 'r');

        if (count($r) == 0)
            return 1000;

        if (count($r) == 1)
            return $r[0];

        return max(...$r);
    }

    private function datas_maxRealTime(array $data_array)
    {
        $max = 0;

        foreach ($data_array as $data)
            $max = max($max, $this->maxRealTime($data));

        return $max;
    }

    private function getPlotters(array $config)
    {
        $ret = [];

        foreach ($config['plotter.factory'] as $fact)
            $ret[] = $fact($this, $config['args'] ?? []);

        return $ret;
    }

    // ========================================================================
    private array $plotters;

    public function plot(array $config)
    {
        $this->plotters = $this->getPlotters($config);
        $csvFiles = [];

        foreach ($this->csvGroupByBasePath as $base => $csvFile) {
            echo "\nPlotting group\n<$base>/\n";
            $groupCsvFiles = \array_map(fn ($f) => "$base/$f.csv", $csvFile);
            \wdPush($base);
            $this->_plotGroup($groupCsvFiles);
            $csvFiles = \array_merge($csvFiles, $groupCsvFiles);
            \wdPop();
        }

        \wdPush($this->workingDir);
        $nbFiles = \count($csvFiles);

        foreach ($this->plotters as $plotter) {

            if ($plotter->getProcessType() === self::PROCESS_FULL) {
                echo "\nPlotting Full ({$plotter->getID()}) with $nbFiles files\n";
                $outDir = "full_{$plotter->getID()}";

                if (! is_dir($outDir))
                    \mkdir($outDir);

                \wdPush($outDir);
                $this->plotFull($csvFiles, $plotter);
                \wdPop();
            }
        }
        \wdPop();
    }

    private function _plotGroup(array $csvFiles)
    {
        foreach ($this->plotters as $plotter) {

            if ($plotter->getProcessType() === self::PROCESS_EACH)
                $this->plotEach($csvFiles, $plotter);
        }
        $this->setData($csvFiles);

        foreach ($this->plotters as $plotter) {

            if ($plotter->getProcessType() === self::PROCESS_GROUP)
                $this->plotGroup($csvFiles, $plotter);
        }
    }

    private function setData(array $csvFiles)
    {
        $this->data = [];

        foreach ($csvFiles as $csvPath) {
            $basePath = \dirname($csvPath);
            $configPath = "$basePath/@config.csv";

            $data = \CSVReader::read($csvPath);

            if (\is_file($configPath))
                $data = \array_merge_recursive($data, \CSVReader::read($configPath));

            $this->data[$csvPath] = $data;
        }
    }

    // ========================================================================
    private function plotFull(array $csvFiles, Plotter\IPlotter $plotter)
    {
        $this->plotFiles($csvFiles, $plotter, \realpath(\dirname($csvFiles[0]) . "/.."), "full_");
    }

    private function plotGroup(array $csvFiles, Plotter\IPlotter $plotter)
    {
        $this->plotFiles($csvFiles, $plotter, \dirname($csvFiles[0]), "all_");
    }

    private function plotFiles(array $csvFiles, Plotter\IPlotter $plotter, string $basePath, string $filePrefix)
    {
        $wdir = \getcwd();
        $data = \array_values($this->data)[0];
        $plotter->plot($csvFiles);
    }

    // ========================================================================
    private function plotEach(array $csvFiles, Plotter\IPlotter $plotter)
    {
        foreach ($csvFiles as $file) {
            $this->setData((array) $file);
            $this->plotOne($file, $plotter);
        }
    }

    private function plotOne(string $csvPath, Plotter\IPlotter $plotter)
    {
        $csvFileName = \basename($csvPath);
        echo "plotting from $csvFileName\n";

        $data = $this->data[$csvPath];
        $plotter->plot((array) $csvPath);
    }

    // ========================================================================
    public static function isTimeMeasure(array $data): bool
    {
        return array_keys($data) === [
            'r',
            'u',
            's',
            'c'
        ];
    }

    public static function plotterFileName(Plotter\IPlotter $plotter, string $csvPath, string $suffix = ''): string
    {
        $fileName = \baseName($csvPath, ".csv");
        return "{$fileName}_{$plotter->getId()}$suffix";
    }

    public function gnuplotSpecialChars(string $s): string
    {
        return \str_replace('_', '\\\\_', $s);
    }
}
