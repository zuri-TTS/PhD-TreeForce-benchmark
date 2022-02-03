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

    private array $plotVariables;

    private array $plotGraphics;

    private array $plotCSVFiles;

    function __construct(string $workingDir, array $csvPaths)
    {
        $this->workingDir = \realpath($workingDir);

        foreach ($csvPaths as $csvPath) {
            $groupByBase[\dirname($csvPath)][] = \basename($csvPath, '.csv');
        }
        foreach ($groupByBase as &$group)
            \natsort($group);

        $this->csvGroupByBasePath = $groupByBase;
        $this->setGraphics([]);
    }

    private function defaultGraphics(): array
    {
        return include __DIR__ . '/graphics.php';
    }

    public function setGraphics(array $graphics)
    {
        $this->graphics = array_merge($this->defaultGraphics(), $graphics);
    }

    public function &getPlotGraphics(): array
    {
        return $this->plotGraphics;
    }

    public function getPlotVariables(): array
    {
        return $this->plotVariables;
    }

    public function getPlotCsvFiles(): array
    {
        return $this->plotCSVFiles;
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
        foreach ($config['plotter.factory'] as $fact)
            $ret[] = $fact($this);

        return $ret;
    }

    // ========================================================================
    private array $plotters;

    public function plot(array $config)
    {
        $wdir = \getcwd();
        $this->plotters = $this->getPlotters($config);
        $csvFiles = [];

        foreach ($this->csvGroupByBasePath as $base => $csvFile) {
            echo "\nPlotting group\n<$base>/\n";
            $groupCsvFiles = \array_map(fn ($f) => "$base/$f.csv", $csvFile);
            \chdir($base);
            $this->_plotGroup($groupCsvFiles);
            $csvFiles = \array_merge($csvFiles, $groupCsvFiles);
        }

        foreach ($this->plotters as $plotter) {

            if ($plotter->getProcessType() === self::PROCESS_FULL)
                $this->plotFull($csvFiles, $plotter);
        }
        \chdir($wdir);
    }

    private function _plotGroup(array $csvFiles)
    {
        foreach ($csvFiles as $csvPath)
            $this->data[$csvPath] = \CSVReader::read($csvPath);

        foreach ($this->plotters as $plotter) {

            if ($plotter->getProcessType() === self::PROCESS_EACH)
                $this->plotEach($csvFiles, $plotter);
        }

        foreach ($this->plotters as $plotter) {

            if ($plotter->getProcessType() === self::PROCESS_GROUP)
                $this->plotGroup($csvFiles, $plotter);
        }
    }

    // ========================================================================
    private function plotFull(array $csvFiles, IPlotter $plotter)
    {
        $this->plotFiles($csvFiles, $plotter, \realpath(\dirname($csvFiles[0]) . "/.."), "full_");
    }

    private function plotGroup(array $csvFiles, IPlotter $plotter)
    {
        $this->plotFiles($csvFiles, $plotter, \dirname($csvFiles[0]), "all_");
    }

    private function plotFiles(array $csvFiles, IPlotter $plotter, string $basePath, string $filePrefix)
    {
        $wdir = \getcwd();
        $data = \array_values($this->data)[0];
        $this->plotVariables = $vars = [
            'time.real.max' => $this->datas_maxRealTime($this->data),
            'time.nb' => $this->nbMeasures($data)
        ];
        $this->plotGraphics = $this->computeGraphics($vars);
        $plotter->plot($csvFiles);
    }

    // ========================================================================
    private function plotEach(array $csvFiles, IPlotter $plotter)
    {
        foreach ($csvFiles as $file)
            $this->plotOne($file, $plotter);
    }

    private function plotOne(string $csvPath, IPlotter $plotter)
    {
        $csvFileName = \basename($csvPath);
        echo "plotting from $csvFileName\n";

        $data = $this->data[$csvPath];
        $this->plotVariables = $vars = [
            'time.real.max' => $this->maxRealTime($data),
            'time.nb' => $this->nbMeasures($data)
        ];
        $this->plotGraphics = $this->computeGraphics($vars);
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

    public static function plotterFileName(IPlotter $plotter, string $csvPath, string $suffix = ''): string
    {
        $fileName = \baseName($csvPath, ".csv");
        return "{$fileName}_{$plotter->getId()}$suffix";
    }

    private function computeGraphics(array $vars): array
    {
        $g = $this->graphics;
        $g = [
            'plot.x.step.nb' => $vars['time.nb'],
            'plot.y.max' => $vars['time.real.max'],
            'queries.nb' => $vars['queries.nb'] ?? 1
        ] + $g;

        $g['plot.x.step.nb'] = (int) ceil(log10($g['plot.y.max']));
        $g['plot.y.step.nb'] = (int) ceil(log10($g['plot.y.max']));

        $g['plot.x.step'] = ($g['bar.w'] * $g['bar.nb']) * $g['queries.nb'];

        $gap = $g['bar.w'] * $g['bar.gap.factor'] * (3 + $g['plot.x.step.nb']);
        $g['plot.w'] = $g['plot.x.step.nb'] * $g['plot.x.step'] + $g['plot.w.space'] + $gap;
        $g['plot.h'] = $g['plot.y.step.nb'] * $g['plot.y.step'] + $g['plot.h.space'];

        $g['plot.x'] = $g['plot.lmargin'];
        $g['plot.y'] = $g['plot.bmargin'];

        $g['plot.w.full'] = $g['plot.w'] + $g['plot.lmargin'];
        $g['plot.h.full'] = $g['plot.h'] + $g['plot.bmargin'];

        $g['w'] = $g['plot.w.full'] + $g['plot.rmargin'];
        $g['h'] = $g['plot.h.full'];
        return $g;
    }

    private function graphics_addBSpace(int $space)
    {
        $g = &$this->plotGraphics;
        $g['h'] += $space;
        $g['plot.y'] += $space;
    }

    public function addFooter(array $footerBlocs): string
    {
        $blocs = \array_map([
            $this,
            'computeFooterBlocGraphics'
        ], $footerBlocs);

        list ($charOffset, $h) = \array_reduce($blocs, fn ($c, $i) => [
            \max($c[0], $i['lines.nb']),
            \max($c[1], $i['h'])
        ], [
            0,
            0
        ]);
        $this->graphics_addBSpace($h);
        $ret = '';

        $x = 0;
        foreach ($blocs as $b) {
            $s = \str_replace('_', '\\\\_', \implode('\\n', $b['bloc']));
            $ret .= "set label \"$s\" at screen 0.01,0.01 offset character $x, character $charOffset\n";
            $x += $b['lines.size.max'];
        }
        return $ret;
    }

    private function computeFooterBlocGraphics(array $bloc): array
    {
        $bloc = \array_map(fn ($v) => empty($v) ? '' : (null === ($v[1] ?? null) ? $v[0] : "$v[0]: $v[1]"), $bloc);
        $maxLineSize = \array_reduce($bloc, fn ($c, $i) => \max($c, strlen($i)), 0);
        $nbLines = \count($bloc);
        $g = $this->plotGraphics;

        return [
            'bloc' => $bloc,
            'lines.nb' => $nbLines,
            'lines.size.max' => $maxLineSize * 0.9,
            'w' => $g['font.size'] * $maxLineSize,
            'h' => ($g['font.size'] + 8) * $nbLines
        ];
    }

    // ========================================================================
    public function getPlotYLines(): string
    {
        $val = $this->getPlotVariables();
        $yMax = $val['time.real.max'];
        $yNbLine = log10($yMax);

        for ($i = 0, $m = 1; $i < $yNbLine; $i ++) {
            $lines[] = "$m ls 0";
            $m *= 10;
        }
        return implode(",\\\n", $lines);
    }

    public function prepareBlocs(array $groups, array $exclude = [], array $val = []): array
    {
        if (empty($val))
            $val = $this->getPlotVariables();

        $blocs = [];

        foreach ($groups as $group) {
            $blocs[] = $this->prepareOneBloc((array) $group, $exclude, $val);
        }
        return $blocs;
    }

    public function prepareOneBloc(array $group, array $exclude = [], array $val = []): array
    {
        if (empty($val))
            $val = $this->getPlotVariables();

        $line = [];

        foreach ((array) $group as $what) {
            $line[] = [
                "[$what]",
                null
            ];

            foreach ($val[$what] as $k => $v) {

                if (in_array($k, $exclude))
                    continue;

                $line[] = [
                    $k,
                    (string) $v
                ];
            }
            $line[] = null;
        }
        return $line;
    }
}
