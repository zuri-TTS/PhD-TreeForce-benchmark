<?php

final class Plot
{

    public const PROCESS_FULL = 'full';

    public const PROCESS_GROUP = 'group';

    public const PROCESS_EACH = 'each';

    private array $data;

    private array $dirToQueriesName;

    private string $workingDir;

    private array $graphics;

    function __construct(string $workingDir)
    {
        if (is_file($workingDir)) {
            $testFiles = [
                $workingDir
            ];
            $workingDir = \dirname($workingDir);
        }

        if (is_dir($workingDir)) {
            $dir = new RecursiveDirectoryIterator($workingDir, //
            FilesystemIterator::KEY_AS_PATHNAME | //
            FilesystemIterator::CURRENT_AS_FILEINFO | //
            FilesystemIterator::FOLLOW_SYMLINKS); //

            $ite = new RecursiveIteratorIterator($dir);
            $ite->setMaxDepth(1);
            $testFiles = [];

            foreach ($ite as $file) {
                $fname = $file->getFilename();
                $testDir = \basename(\dirname($file->getRealPath()));

                if (\preg_match('#^([^@][^/]*)\.csv$#', $fname, $matches))
                    $testFiles[] = "$testDir/{$matches[1]}";
            }
        } else
            throw new \Exception("Plot: can't handle '$workingDir'");

        $this->workingDir = \realpath($workingDir);

        foreach ($testFiles as $ftest) {
            $dirName = \dirname($ftest);

            if (\str_starts_with($dirName, 'full_'))
                continue;

            $groupByBase[$dirName][] = \basename($ftest);
        }
        foreach ($groupByBase as &$group) {
            \natsort($group);
            $group = \array_values($group);
        }
        $this->dirToQueriesName = $groupByBase;
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
        $plotters = $this->getPlotters($config);
        \wdPush($this->workingDir);

        $groupPlotters = \array_filter($plotters, fn ($p) => $p instanceof \Plotter\IGroupPlotter);

        if (! empty($groupPlotters)) {
            $this->plotters = $groupPlotters;

            foreach ($this->dirToQueriesName as $testDir => $queriesName) {
                echo "\nPlotting group\n<$testDir>/\n";
                $this->_plotGroup($testDir, $queriesName);
            }
        }
        $fullPlotters = \array_filter($plotters, fn ($p) => $p instanceof \Plotter\IFullPlotter);

        if (! empty($fullPlotters)) {

            $tests = [];
            foreach ($this->dirToQueriesName as $testDir => $queriesName)
                foreach ($queriesName as $qname)
                    $tests[] = "$testDir/$qname";

            $nbFiles = \count($tests);

            $this->data = [];
            foreach ($this->dirToQueriesName as $testDir => $queriesName) {
                $this->data = \array_merge($this->data, $this->makeData($testDir, $queriesName));
            }
            foreach ($fullPlotters as $plotter) {

                echo "\nPlotting Full ({$plotter->getID()}) with $nbFiles files\n";
                $outDir = "full_{$plotter->getID()}";

                if (! is_dir($outDir))
                    \mkdir($outDir);

                \wdPush($outDir);
                $this->plotFull($tests, $plotter);
                \wdPop();
            }
        }
        \wdPop();
    }

    private function _plotGroup(string $dirName, array $queriesName)
    {
        foreach ($this->plotters as $plotter) {

            if ($plotter->getProcessType() === self::PROCESS_EACH)
                $this->plotEach($dirName, $queriesName, $plotter);
        }
        $this->setData($dirName, $queriesName);

        foreach ($this->plotters as $plotter) {

            if ($plotter->getProcessType() === self::PROCESS_GROUP)
                $this->plotGroup($dirName, $queriesName, $plotter);
        }
    }

    private function makeData(string $dirName, array $queriesName): array
    {
        $ret = [];
        $measures = new Measures($dirName);

        foreach ($queriesName as $qname)
            $ret["$dirName/$qname"] = $measures->loadMeasuresOf($qname);

        return $ret;
    }

    private function setData(string $dirName, array $queriesName)
    {
        $this->data = $this->makeData($dirName, $queriesName);
    }

    // ========================================================================
    private function plotFull(array $tests, Plotter\IPlotter $plotter)
    {
        $plotter->plot($tests);
    }

    private function plotGroup(string $dirName, array $queriesName, Plotter\IPlotter $plotter)
    {
        $plotter->plot($dirName, $queriesName);
    }

    // ========================================================================
    private function plotEach(string $dirName, array $queriesName, Plotter\IPlotter $plotter)
    {
        foreach ($queriesName as $qname) {
            $this->setData($dirName, (array) $qname);
            $this->plotOne($dirName, $qname, $plotter);
        }
    }

    private function plotOne(string $dirName, string $queryName, Plotter\IPlotter $plotter)
    {
        echo "plotting $dirName/$queryName\n";

        $plotter->plot($dirName, (array) $queryName);
    }

    // ========================================================================
    public static function plotterFileName(Plotter\IPlotter $plotter, string $queryName, string $suffix = ''): string
    {
        return "{$queryName}_{$plotter->getId()}$suffix";
    }

    public function gnuplotSpecialChars(string $s): string
    {
        return \str_replace('_', '\\\\_', $s);
    }
}
