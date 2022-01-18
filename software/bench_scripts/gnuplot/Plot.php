<?php
include_once __DIR__ . '/CSVReader.php';

final class Plot
{

    private array $infos = [
        'time.real.max' => 0
    ];

    private array $data;

    private array $csvGroupByBasePath;

    private string $workingDir;

    private array $graphics;

    private array $plotGraphics;

    private array $plotVariables;

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

    private function preparePlotsConfig(array $config): array
    {
        $configsPlot = $config['plots'];

        if (isset($configsPlot[0])) {
            $configPlot_default = $configsPlot['default'] ?? [];
            unset($configsPlot['default']);

            foreach ($configsPlot as &$c)
                $c += $configPlot_default;
        } else
            $configsPlot = [
                $configsPlot
            ];

        return $configsPlot;
    }

    private function makeCsvPaths(string $csvPath, array $plotConfig)
    {
        $id = $plotConfig['id'];
        $fileName = \baseName($csvPath, ".csv");
        $basePath = \dirname($csvPath);
        $data = $this->data[$csvPath];

        $dataFileName = "{$fileName}_$id.dat";
        $dataFilePath = "$basePath/$dataFileName";

        $outFileName = "{$fileName}_$id.{$plotConfig['terminal.type']}";
        $outFilePath = "$basePath/$outFileName";

        $plotFileName = "{$fileName}_$id.plot";
        $plotFilePath = "$basePath/$plotFileName";

        return [
            'out.base.path' => $basePath,

            'plot.file.path' => $plotFilePath,
            'plot.file.name' => $plotFileName,

            'data.file.path' => $dataFilePath,
            'data.file.name' => $dataFileName,

            'out.file.path' => $outFilePath,
            'out.file.name' => $outFileName,

            'file.name' => $fileName,
            'data' => $data,
            'time.real.max' => $this->maxRealTime($data),
            'time.nb' => $this->nbMeasures($data)
        ] + [
            'bench' => $data['bench'],
            'answers' => $data['answers'] ?? [],
            'queries' => $data['queries'] ?? []
        ];
    }

    // ========================================================================
    public function plot(array $config)
    {
        foreach ($this->csvGroupByBasePath as $base => $csvFile) {
            echo "\n$base/\n";
            $this->plotGroup($config, \array_map(fn ($f) => "$base/$f.csv", $csvFile));
        }
    }

    private function makeInfos()
    {
        $max = 0;

        foreach ($this->data as $data)
            $max = max($max, $this->maxRealTime($data));

        $this->infos['time.real.max'] = $max;
    }

    private function plotGroup(array $config, array $csvFiles)
    {
        $wdir = \getcwd();

        foreach ($csvFiles as $csvPath)
            $this->data[$csvPath] = \CSVReader::read($csvPath);

        $this->makeInfos();
        $plotConfigs = $this->preparePlotsConfig($config);

        foreach ($csvFiles as $csvPath)
            $this->plotEach($csvPath, $plotConfigs);

        $this->plotAll($csvFiles, $plotConfigs);
        \chdir($wdir);
    }

    // ========================================================================
    private function plotAll(array $csvFiles, array $plotConfigs)
    {
        foreach ($plotConfigs as $plotConfig) {

            if (($plotConfig['process'] ?? '') === 'all')
                $this->plotAll_($csvFiles, $plotConfig);
        }
    }

    private function plotAll_(array $csvFiles, array $plotConfig)
    {
        $id = $plotConfig['id'];

        foreach ($csvFiles as $csvPath)
            $paths[$csvPath] = $this->makeCsvPaths($csvPath, $plotConfig);

        $vars = $plotConfig;
        $vars['files'] = $paths;
        $basePath = dirname($csvFiles[0]);
        \chdir($basePath);

        $fName = "all_$id";
        $plotFileName = "$fName.plot";
        $plotFilePath = "{$basePath}/$plotFileName";
        $outFileName = "$fName.{$plotConfig['terminal.type']}";
        $outFilePath = "{$basePath}/$outFileName";

        $vars = array_merge($vars, [
            'out.base.path' => basename($basePath),
            'out.file.name' => $outFileName,
            'out.file.path' => $outFilePath,
            'plot.file.name' => $plotFileName,
            'plot.file.path' => $plotFilePath,
            'bench' => $paths[$csvFiles[0]]['data']['bench'],
            'time.nb' => $this->nbMeasures($paths[$csvFiles[0]]['data']),
            'queries.nb' => count($csvFiles)
        ], $this->infos);

        $this->plotGraphics = $this->computeGraphics($vars);
        $this->plotVariables = $vars;

        $contents = \get_include_contents($plotConfig['template.path'], [
            'PLOT' => $this
        ]);
        \file_put_contents($plotFilePath, $contents);

        $cmd = "gnuplot '$plotFilePath' > '$outFilePath'";
        echo "plotting {$vars['out.file.name']}\n";
        system($cmd);
    }

    // ========================================================================
    private function plotEach(string $csvPath, array $plotConfigs)
    {
        foreach ($plotConfigs as $plotConfig) {

            if (($plotConfig['process'] ?? '') !== 'all')
                $this->plotOne($csvPath, $plotConfig);
        }
    }

    private function plotOne(string $csvPath, array $plotConfig)
    {
        $vars = $this->makeCsvPaths($csvPath, $plotConfig) + $plotConfig;
        \chdir($vars['out.base.path']);
        $plotFilePath = $vars['plot.file.path'];
        $outFilePath = $vars['out.file.path'];
        $dataFilePath = $vars['data.file.path'];

        $this->plotGraphics = $this->computeGraphics($vars);
        $this->plotVariables = $vars;

        \file_put_contents($dataFilePath, get_ob(fn () => $vars['data.plot']($vars)));

        $contents = \get_include_contents($plotConfig['template.path'], [
            'PLOT' => $this
        ]);

        \file_put_contents($plotFilePath, $contents);

        $cmd = "gnuplot '$plotFilePath' > '$outFilePath'";
        echo "plotting {$vars['out.file.name']}\n";

        system($cmd);
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
            'lines.size.max' => $maxLineSize,
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
