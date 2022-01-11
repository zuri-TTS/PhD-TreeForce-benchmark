<?php

final class Benchmark
{

    private const dateFormat = DateTime::ATOM;

    private array $config;

    private string $cmd;

    private string $tmpOutFile;

    private string $qOutputPath;

    private array $descriptors;

    private array $javaOutput;

    private static $timeMeasure = [
        'r',
        'u',
        's',
        'c'
    ];

    function __construct(array $config)
    {
        $this->config = $config;

        $jarPath = \escapeshellarg($config['jar.path']);
        $appCmd = \escapeshellarg($config['app.cmd']);

        $opt = $config['java.opt'];
        $this->cmd = "java $opt -jar $jarPath $appCmd -c std://in";

        $this->tmpOutFile = \tempnam(sys_get_temp_dir(), 'tf_');
        $this->descriptors = $descriptorspec = array(
            0 => [
                "pipe",
                "r"
            ],
            1 => [
                'file',
                $this->tmpOutFile,
                'w+'
            ],
            2 => STDOUT
        );
        $this->qOutputPath = $config['java.properties']['output.path'];

        // Create the output dir
        if (! \is_dir($this->qOutputPath)) {
            \mkdir($this->qOutputPath, 0777, true);
            $this->writeBenchConfigToCSV(new SplFileObject("{$this->qOutputPath}/@config.csv", "w"));
        }
    }

    function __destruct()
    {
        \unlink($this->tmpOutFile);
    }

    private function writeJavaProperties(array $incVar)
    {
        $s = "";

        foreach ($this->config['java.properties'] + $incVar as $k => $v) {
            $s .= "$k: $v\n";
        }
        return $s;
    }

    function parseJavaOutput(): array
    {
        $lines = \file($this->tmpOutFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        $ret = [];
        $p = &$ret['default'];

        foreach ($lines as $line) {
            if (preg_match("#\[(.+)\]#", $line, $matches)) {
                $group = $matches[1];
                $p = &$ret[$group];
            } else if (preg_match("#([^\s=:]+)[\s=:]+([^\s]+)#", $line, $matches)) {
                $var = $matches[1];
                $val = $matches[2];

                if (\is_numeric($val))
                    $val = (int) $val;

                $p[$var] = $val;
            }
        }
        $ret['measures'] = $this->appExtractMeasures($ret['measures'] ?? []);
        return $ret;
    }

    function appExtractMeasures($measures): array
    {
        foreach ($measures as &$meas) {
            $time = $meas;
            $meas = [];

            foreach ($this::$timeMeasure as $tm) {
                \preg_match("/$tm(-?\d+)/", $time, $capture, PREG_OFFSET_CAPTURE);
                $meas[$tm] = (int) $capture[1][0];
            }
        }
        return $measures;
    }

    private function getFiles(): array
    {
        $config = $this->config;
        $dir = $config['java.properties']['queries.dir'];

        if (\is_file($dir)) {
            $queries = [
                \basename($dir)
            ];
            $dir = \dirname($dir);
        } else {
            $files = \scandir($dir);
            $queries = \array_filter($files, fn ($f) => \is_file("$dir/$f"));

            // Filter queries already processed in the output dir
            $tmpFiles = \scandir($this->qOutputPath);
            $tmpResultFiles = \array_values(iterator_to_array(new \RegexIterator(new \ArrayIterator($tmpFiles), "#^.+\.csv$#")));
            $queries = \array_filter($queries, function ($e) use ($tmpResultFiles): bool {
                return ! \in_array("$e.csv", $tmpResultFiles);
            });
            \natsort($queries);
        }
        return [
            'queries' => $queries,
            'rule' => $config['java.properties']['rules']
        ];
    }

    private static function avg(array $vals): int
    {
        $c = count($vals);

        if ($c == 0)
            return 0;

        return round(array_sum($vals) / $c);
    }

    private function writeBenchConfigToCSV(SplFileObject $csvFile)
    {
        $jprop = $this->config['java.properties'];
        $basePathEndOffset = \strlen($this->config['java.properties']['base.path']) + 1;
        $csvFile->fputcsv([
            'bench'
        ]);
        $dataSet = \array_slice(\explode('/', $this->qOutputPath), - 3, - 1);
        $csvFile->fputcsv([
            'dataset',
            implode('/', $dataSet)
        ]);
        $csvFile->fputcsv([
            'datetime',
            $this->config['bench.datetime']->format(self::dateFormat)
        ]);
        $csvFile->fputcsv([
            'output.dir',
            $this->config['bench.output.dir']
        ]);
        $csvFile->fputcsv([
            'rules',
            \substr($jprop['rules'] ?? '', $basePathEndOffset)
        ]);
        $csvFile->fputcsv([
            'summary',
            \substr($jprop['summary'] ?? '', $basePathEndOffset)
        ]);
        $csvFile->fputcsv([
            'query.native',
            \substr($jprop['query.native'] ?? '', $basePathEndOffset)
        ]);
        $csvFile->fputcsv([
            'querying.each',
            $jprop['querying.each'] ?? ''
        ]);
        $csvFile->fputcsv([
            'querying.display.answers',
            $jprop['querying.display.answers'] ?? ''
        ]);
        $csvFile->fputcsv([
            'inhibitBatchStreamTime',
            $jprop['inhibitBatchStreamTime'] ?? ''
        ]);
        $csvFile->fputcsv([
            'toNative.dots',
            $jprop['toNative.dots'] ?? ''
        ]);
        $csvFile->fputcsv([
            'leaf.checkTerminal',
            $jprop['leaf.checkTerminal'] ?? ''
        ]);
        $csvFile->fputcsv([
            'query.batchSize',
            $jprop['query.batchSize'] ?? ''
        ]);
        $csvFile->fputcsv([
            'data.batchSize',
            $jprop['data.batchSize'] ?? ''
        ]);
    }

    private function writeCSV(string $queryFile, array $measures)
    {
        $usedMeasures = $this->forgetMeasures($queryFile, $measures);

        if (empty($usedMeasures))
            return;

        $usedMeasures = \array_column($usedMeasures, 'measures');
        $columns = \array_keys($usedMeasures[0]);
        $avg = [];

        /*
         * Aggregate infos (average, ...)
         */
        foreach ($columns as $column) {
            $colMeasures = \array_column($usedMeasures, $column);
            $avg[$column] = [];

            foreach ($this::$timeMeasure as $tm)
                $avg[$column][$tm] = $this::avg(\array_column($colMeasures, $tm));
        }

        // Make the query file
        $qfile = new \SplFileObject("$this->qOutputPath/$queryFile.csv", 'w');

        $measureConfig = $this->getMeasuresConfig($queryFile);
        $forgetNb = (int) $measureConfig['forget'];
        $forgetLine = \array_fill(0, $forgetNb, 'forget');
        $forgetLine = \array_merge($forgetLine, \array_fill(0, count($usedMeasures), null));
        $forgetLine = \array_merge($forgetLine, \array_fill(0, $forgetNb, 'forget'));
        {
            $this->writeBenchConfigToCSV($qfile);
            $qfile->fputcsv([
                'measures.nb',
                $measureConfig['nb'] ?? ''
            ]);
            $qfile->fputcsv([
                'measures.forget',
                $measureConfig['forget'] ?? ''
            ]);
        }
        $qfile->fputcsv([]);

        { // Print no-time measures
            $columns = \array_keys($measures[0]);
            unset($columns[\array_search('measures', $columns, true)]);

            foreach ($columns as $column) {
                $cols = \array_column($measures, $column);

                if (empty($cols[0]))
                    continue;

                $items = \array_keys($cols[0]);
                $qfile->fputcsv([
                    $column
                ]);

                foreach ($items as $item)
                    $qfile->fputcsv(\array_merge([
                        $item
                    ], \array_column($cols, $item)));

                $qfile->fputcsv([]);
            }
        }
        { // Print time measures
            $columns = \array_keys($usedMeasures[0]);
            $allMeasures = \array_column($measures, 'measures');

            foreach ($columns as $column) {
                $colMeasures = \array_column($allMeasures, $column);

                $qfile->fputcsv(\array_merge([
                    $column,
                    'avg'
                ], $forgetLine));

                foreach ($this::$timeMeasure as $tm) {
                    $tmp = \array_merge([
                        "$tm",
                        $avg[$column][$tm]
                    ], \array_column($colMeasures, $tm));
                    $qfile->fputcsv($tmp);
                }
                $qfile->fputcsv([]);
            }
        }
    }

    private function forgetMeasures(string $queryFile, array $measures): array
    {
        $forget = (int) $this->getMeasuresConfig($queryFile)['forget'];

        if ($forget > 0)
            return array_slice($measures, $forget, - $forget);
        else
            return $measures;
    }

    private function prepareIncVars(string $query = '')
    {
        $config = $this->config;

        if (! empty($n = $config['bench.query.native.pattern']))
            $native = sprintf($n, $query);

        return [
            'query.name' => $query,
            'query.native' => $native ?? ''
        ];
    }

    private function getMeasuresConfig(string $queryFile)
    {
        $config = $this->config;
        return $config['bench.measures'][$queryFile] ?? $config['bench.measures']['default'];
    }

    private function executeMeasures(string $queryFile)
    {
        $config = $this->config;
        $cold = $config['bench.cold'];
        $measures = [];
        $confMeasure = $this->getMeasuresConfig($queryFile);
        $nbMeasures = (int) $confMeasure['nb'];

        $incVars = $this->prepareIncVars($queryFile);

        while ($nbMeasures --) {

            if ($cold)
                $config['bench.cold.function']();

            // if ($nbMeasures === 0)
            // $incVars['querying_config_print'] = 'y';

            echo $confMeasure['nb'] - $nbMeasures, "/", $confMeasure['nb'], "\n";

            $proc = \proc_open($this->cmd, $this->descriptors, $pipes);
            \fwrite($pipes[0], $this->writeJavaProperties($incVars));
            \fclose($pipes[0]);
            $cmdReturn = \proc_close($proc);

            if (0 !== $cmdReturn)
                exit($cmdReturn);

            \readfile($this->tmpOutFile);
            $measures[] = $this->parseJavaOutput();
        }
        \usort($measures, function ($a, $b) {
            return $a['measures']['command']['u'] - $b['measures']['command']['u'];
        });
        return $measures;
    }

    public function executeOnce()
    {
        $incVars = $this->prepareIncVars();
        $descriptors = $this->descriptors;
        $descriptors[1] = STDOUT;

        echo $this->cmd, "\n";

        $proc = \proc_open($this->cmd, $descriptors, $pipes);
        \fwrite($pipes[0], $this->writeJavaProperties($incVars));
        \fclose($pipes[0]);
        $cmdReturn = \proc_close($proc);

        if (0 !== $cmdReturn)
            \fputs(STDERR, "Return code: $cmdReturn\n");
    }

    public function doTheBenchmark()
    {
        $queryFiles = $this->getFiles();
        $queries = $queryFiles['queries'];
        $dataSet = $this->config['dataSet'];

        // Build the command
        echo $this->cmd, "\n\n";

        foreach ($queries as $query) {
            echo "==================================\n";
            echo "<{$dataSet->getId()}>\n";
            echo "$query\n\n";

            $queryFile = $query;
            $measures = $this->executeMeasures($query);

            $this->writeCSV($query, $measures);
        }
        $this->plot();
    }

    private function plot(): void
    {
        $argv[] = 'plot.php';
        $argv[] = $this->qOutputPath;

        include __DIR__ . "/../plot.php";
    }
}
