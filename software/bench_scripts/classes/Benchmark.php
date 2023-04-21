<?php

final class Benchmark
{

    private const dateFormat = DateTime::ATOM;

    private array $config;

    private string $cmd;

    private string $qOutputPath;

    private array $descriptors;

    private array $javaOutput;

    private bool $timeoutAllQueries;

    private bool $timeout = false;

    function __construct(array $config)
    {
        $this->config = $config;

        $jarPath = \escapeshellarg($config['jar.path']);
        $appCmd = \escapeshellarg($config['app.cmd']);

        $opt = $config['java.opt'];
        $this->cmd = "java $opt -jar $jarPath $appCmd -c std://in";

        $this->descriptors = $descriptorspec = array(
            0 => [
                "pipe",
                "r"
            ],
            1 => STDOUT,
            2 => STDOUT
        );
        $this->qOutputPath = $config['java.properties']['output.path'];
    }

    private function createAndWdPushOutputDir(): void
    {
        if (! \is_dir($this->qOutputPath)) {
            \mkdir($this->qOutputPath, 0777, true);

            // Write some bench params that don't appears directly elsewhere
            $bconf = [
                'bench' => [
                    'dataset' => $this->config['dataSet']->id(),
                    'datetime' => $this->config['bench.datetime']->format(self::dateFormat)
                ]
            ];
        }
        \wdPush($this->qOutputPath);
        $fconfig = \fopen("bench_config.txt", "w");
        \Measures::writeAsJavaProperties($bconf, $fconfig);
        fclose($fconfig);
    }

    public function getExistings(): array
    {
        $outPath = \dirname($this->qOutputPath);

        if (! \is_dir($outPath))
            return [];

        $regex = $this->config['bench.output.pattern'];
        $regex = \str_replace([
            '[',
            ']',
            '(',
            ')'
        ], [
            '\[',
            '\]',
            '\(',
            '\)'
        ], $regex);
        $regex = sprintf($regex, '[^\]]+');
        $a = $files = \scandirNoPoints($outPath);
        $files = \array_filter($files, fn ($f) => \preg_match("#^$regex$#", $f));
        return $files;
    }

    private function writeJavaProperties(array $incVar)
    {
        $jprop = $this->config['java.properties'] + $incVar;
        $str = fopen("php://memory", "r+");
        Measures::writeAsJavaProperties($jprop, $str);
        \rewind($str);
        return \stream_get_contents($str);
    }

    private function parseJavaOutput(string $measuresFile): array
    {
        $lines = \file($measuresFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $ret = \Measures::parseLinesOfMeasures($lines);
        return $ret;
    }

    // ========================================================================
    private static function avg(array $vals): int
    {
        $c = count($vals);

        if ($c == 0)
            return 0;

        return round(array_sum($vals) / $c);
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
            'bench.measures.i' => 1,
            'query.name' => $query
            // 'query.native' => $native ?? ''
        ];
    }

    private function getMeasuresConfig(string $queryFile)
    {
        $config = $this->config;
        return $config['bench.measures'][$queryFile] ?? $config['bench.measures']['default'];
    }

    private function executeMeasures(string $queryFile, string $header, ?int $forceNbMeasures = null)
    {
        $config = $this->config;
        $cold = $config['bench.cold'];

        if (isset($forceNbMeasures)) {
            $nbMeasures = $totalMeasures = 1;
        } else {
            $confMeasure = $this->getMeasuresConfig($queryFile);
            $nbMeasures = $totalMeasures = $confMeasure['nb'];
        }

        $incVars = $this->prepareIncVars($queryFile);
        $i = 1;

        while ($nbMeasures --) {
            $this->timeout = false;

            if ($cold)
                $config['bench.cold.function']();

            $outFile = "{$incVars['query.name']}_measures-{$incVars['bench.measures.i']}.txt";
            echo $totalMeasures - $nbMeasures, "/$totalMeasures $header\n";

            $proc = \proc_open($this->cmd, $this->descriptors, $pipes);
            \fwrite($pipes[0], $this->writeJavaProperties($incVars));
            \fclose($pipes[0]);
            $cmdReturn = \proc_close($proc);
            $incVars['bench.measures.i'] ++;

            if (0 !== $cmdReturn)
                exit($cmdReturn);

            if ($this->config['app.output.display'])
                \readfile($this->tmpOutFile);

            $measures = \Measures::parseLinesOfMeasures(\file($outFile));

            // No query case: we can skip because hirule asks nothing to the document-store
            if ((int) $measures['queries']['total'] === 0) {
                echo "Skipped because no queries to send\n";
                $nbMeasures = 0;
            } elseif (isset($measures['error.timeout'])) {
                echo "Skipped because of timeout `{$measures['error.timeout']['value']}ms`\n";
                $this->timeout = true;
                $nbMeasures = 0;
            }
        }
    }

    public function printJavaConfig(): void
    {
        $incVars = $this->prepareIncVars();
        echo $this->writeJavaProperties($incVars);
    }

    public function executeOnce()
    {
        $this->createAndWdPushOutputDir();
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

        \wdPop();
    }

    public function doTheBenchmark(?int $forceNbMeasures = null)
    {
        $this->createAndWdPushOutputDir();
        $queries = $this->config['dataSet']->getQueries();

        $qorder = (array) $this->config['timeout.order.queries'];
        $this->timeoutAllQueries = ! empty($qorder);
        $qorder = \array_intersect($qorder, $queries);
        $queries = \array_merge($qorder, \array_diff($queries, $qorder));

        echo $this->cmd, "\n\n";

        foreach ($queries as $query) {
            $header = "<{$this->config['dataSet']}> ({$this->config['app.cmd']}) query: $query";
            $this->executeMeasures($query, $header, $forceNbMeasures);
            echo "\n";

            if ($this->timeout && $this->timeoutAllQueries) {
                $q = \implode(',', $queries);
                echo "End of TEST because of timeout.order.queries: [$q]\n";
                break;
            }
        }
        \wdPop();
    }
}
