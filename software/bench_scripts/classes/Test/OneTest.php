<?php
namespace Test;

final class OneTest extends AbstractTest
{

    private ?int $forceNbMeasures = null;

    private array $testConfig;

    private bool $doonce = false;

    private bool $needDatabase = false;

    private bool $needSummary = false;

    private array $args;

    public function __construct(\DataSet $ds, string $collectionName, CmdArgs $cmdParser)
    {
        parent::__construct($ds, $collectionName, $cmdParser);

        $parsed = $this->cmdParser->parsed();
        $args = &$parsed['args'];
        $javaProperties = $parsed['javaProperties'];

        $this->doonce = $args['doonce'];

        if ($args['cmd'] === 'generate')
            $this->needSummary = true;

        if (\in_array($args['cmd'], [
            'generate',
            'config'
        ]))
            $this->needDatabase = false;
        else
            $this->needDatabase = true;

        if (\in_array($args['cmd'], [
            'generate',
            'config'
        ]) || \in_array($javaProperties['querying.mode'], [
            'stats'
        ])) {
            $args['cmd-display-output'] = true;
            $args['plot'] = false;
            $this->forceNbMeasures = 1;
        }

        if ($args['forget-results'])
            $args['plot'] = false;

        $this->testConfig = \makeConfig($this->ds, $this->collection, $args, $parsed['javaProperties']);
        $this->args = $args;
    }

    public function getTestConfig(): array
    {
        return $this->testConfig;
    }

    public function execute()
    {
        $header = \str_repeat('=', \strlen((string) $this->ds));
        echo "\n$header\nTEST\n$this->collection\n";

        if (! empty($this->testConfig['test.existing'])) {
            echo "Similar test already exists:\n";

            foreach ($this->testConfig['test.existing'] as $test)
                echo $test, "\n";

            return;
        }

        try {
            $this->preProcess();
            $bench = new \Benchmark($this->testConfig);

            if ($this->doonce)
                $bench->executeOnce();
            else
                $bench->doTheBenchmark($this->forceNbMeasures);

            $this->postProcess();
        } catch (\Exception $e) {
            $this->errors[] = [
                'dataset' => $this->ds,
                'collection' => $this->collection,
                'exception' => $e
            ];
            \fwrite(STDERR, "<$this->ds>Exception:\n {$e->getMessage()}\n");
        }
    }

    // =======================================================================
    private function preProcess()
    {
        $args = $this->args;

        if ($this->needDatabase) {
            $collExists = $this->collectionExists();

            if ($args['pre-clean-db'] || $args['clean-db']) {
                $this->dropCollection();
                $collExists = $this->collectionExists();
            }
            if ($args['generate-dataset']) {

                if (! $collExists) {
                    $this->loadCollection();
                    $collExists = $this->collectionExists();
                }
            }

            if (! $collExists)
                throw new \Exception("The collection treeforce.$this->collection must exists in the database");
        }
        if ($this->needDatabase || $this->needSummary) {
            $cmdSummarize = $args['cmd'] === 'summarize';

            if (! $cmdSummarize) {
                $this->ensureSummary($args['summary']);
                $this->ensureSummary((string) $args['toNative_summary']);
                $this->checkSummary($this->testConfig['summary']);
                $this->checkSummary($this->testConfig['toNative.summary']);
            }
        }
    }

    private function ensureSummary(string $type): void
    {
        if (empty($type))
            return;

        DoSummarize::summarize($this->ds, $this->collection, $type);
    }

    private function checkSummary(string $path): void
    {
        if (empty($type))
            return;

        if (! \is_file($path))
            throw new \Exception("Summary '$summaryPath' does not exists");
    }

    private function postProcess()
    {
        $args = $this->args;
        $config = $this->testConfig;

        if ($args['post-clean-db'] || $args['clean-db'])
            $this->dropCollection();

        if ($args['forget-results'] && \is_dir($config['bench.output.path']))
            \rrmdir($config['bench.output.path']);
        else
            \touch($config['bench.output.path'].'/@end');
    }
}