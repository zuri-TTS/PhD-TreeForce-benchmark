<?php
namespace Test;

final class OneTest extends AbstractTest
{

    private ?int $forceNbMeasures = null;

    private array $testConfig;

    private bool $doonce = false;

    private bool $needDatabase = false;

    private bool $needNativeSummary = false;

    private bool $needSummary = false;

    private bool $displayHeader = true;

    private array $args;

    public function __construct(\DataSet $ds, \Data\IPartition $partition, CmdArgs $cmdParser)
    {
        parent::__construct($ds, $partition, $cmdParser);

        $parsed = $this->cmdParser->parsed();
        $args = &$parsed['args'];
        $javaProperties = $parsed['javaProperties'];

        $this->doonce = $args['doonce'];

        if (\in_array($args['cmd'], [
            'partition'
        ])) {
            $this->needNativeSummary = true;
        }

        if (\in_array($args['cmd'], [
            'generate',
            'querying'
        ])) {
            $this->needSummary = true;
            $this->needNativeSummary = true;
        }

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

        $this->testConfig = \makeConfig($this->ds, $partition, $args, $parsed['javaProperties']);
        $this->args = $args;
    }

    public function setDisplayHeader(bool $val)
    {
        $this->displayHeader = $val;
    }

    public function getTestConfig(): array
    {
        return $this->testConfig;
    }

    public function execute()
    {
        if ($this->displayHeader) {
            $title = "$this->collection/{$this->partition->getID()}";
            $header = \str_repeat('=', \strlen($title));
            echo "\n$header\nTEST\n$title\n";
        }

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
        $testConfig = $this->testConfig;

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

        if ($this->needNativeSummary) {
            $partition = $this->partition;

            if ($this->args['cmd'] === 'partition')
                $partition = $partition->getPhysicalParent();

            $this->ensureSummary((string) $args['toNative_summary'], $partition);
            $this->checkSummary($this->testConfig['toNative.summary']);
        }
        if ($this->needSummary) {
            $this->ensureSummary($args['summary'], $this->partition);

            foreach ((array) $this->testConfig['summary'] as $summary)
                $this->checkSummary($summary);
        }
    }

    private function ensurePartition(string $partition): void
    {
        if (empty($type))
            return;

        DoSummarize::summarize($this->ds, $this->partition, $type);
    }

    private function ensureSummary(string $summary, \Data\IPartition $partition): void
    {
        if (empty($summary))
            return;

        DoSummarize::summarize($this->ds, $partition, $summary);
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
            \touch($config['bench.output.path'] . '/@end');
    }
}