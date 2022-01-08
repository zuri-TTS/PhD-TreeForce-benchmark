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

    // ========================================================================
    private function nbMeasures(array $data)
    {
        return count(\array_filter($data, "self::isTimeMeasure"));
    }

    private function maxRealTime(array $data)
    {
        $r = \array_column(\array_filter($data, "self::isTimeMeasure"), 'r');
        
        if(count($r) == 1)
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
            'bench' => $data['bench']
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

        $contents = \get_include_contents($plotConfig['template.path'], $vars, 'val');
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

        \file_put_contents($dataFilePath, get_ob(fn () => $vars['data.plot']($vars)));
        $contents = \get_include_contents($plotConfig['template.path'], $vars, 'val');

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

    public static function gnuplot_setTerminal(array $val): string
    {
        if (! isset($val['terminal.type']))
            return "";

        $ret = "set terminal {$val['terminal.type']}";

        if (isset($val['terminal.size']))
            $ret .= " size {$val['terminal.size']}";

        $ret .= "\n";
        return $ret;
    }
}