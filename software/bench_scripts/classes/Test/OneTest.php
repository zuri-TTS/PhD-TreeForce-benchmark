<?php
namespace Test;

final class OneTest extends AbstractTest
{

    private ?int $forceNbMeasures = null;

    private array $testConfig;

    private bool $doonce = false;

    private bool $needDatabase = false;

    private bool $needNativeSummary = false;

    private bool $needPartition = false;

    private bool $needSummary = false;

    private bool $displayHeader = true;

    private string $cleanDBGlob;

    private array $args;

    private array $javaProperties;

    public function __construct(\DataSet $ds, CmdArgs $cmdParser, \Data\IPartition ...$partitions)
    {
        parent::__construct($ds, $cmdParser, ...$partitions);

        $parsed = $this->cmdParser->parsed();
        $args = &$parsed['args'];
        $javaProperties = $parsed['javaProperties'];

        $this->doonce = $args['doonce'];
        $this->cleanDBGlob = $args['clean-db-json'] ? '*.json' : '';

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
            $this->needPartition = $partitions[0]->isLogical();
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

        $this->testConfig = \makeConfig($this->ds, $partitions, $cmdParser);
        $this->args = $args;
        $this->javaProperties = $javaProperties;
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
            $partition = count($this->partitions) == 1 ? ".{$this->partitions[0]->getID()}" : null;
            $title = "{$this->ds}$partition";
            $header = \str_repeat('=', \strlen($title));
            echo "\n$header\nTEST\n$title\n";

            foreach (\Test\CmdArgs::expandables() as $group => $expandables)
                foreach ($expandables as $exp)
                    echo "$exp: {$this->cmdParser[$group][$exp]}\n";
        }
        if (! empty($this->testConfig['test.existing'])) {
            echo "Similar test already exists:\n";

            foreach ($this->testConfig['test.existing'] as $test)
                echo $test, "\n";

            $this->cmdParser['skipped'] = true;
            $this->postCleanDb();
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
                'collections' => $this->getCollectionsName(),
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
        $partition = $this->partitions[0];

        if ($this->needDatabase) {
            $collExists = $this->collectionsExists();

            if ($args['pre-clean-db'] || $args['clean-db']) {
                $this->dropCollections($this->cleanDBGlob);
                $collExists = $this->collectionsExists();
            }
            if ($args['generate-dataset']) {

                if (! $collExists) {
                    $this->loadCollections();
                    $collExists = $this->collectionsExists();
                }
            }

            if (! $collExists)
                throw new \Exception("The collection treeforce.$this->collection must exists in the database");

            // Load the index
            if ($this->args['cmd'] === 'querying' && $partition->isLogical()) {
                $partitionId = $this->javaProperties['partition.id'];
                $this->loadIndex($partitionId);
            }
        }

        if ($this->needNativeSummary) {

            if ($this->args['cmd'] === 'partition' && $partition->isLogical())
                $partition = $partition->getPhysicalParent();

            $this->ensureSummary((string) $args['toNative_summary'], $partition, 0);
            $this->checkSummary($this->testConfig['toNative.summary']);
        }
        if ($this->needSummary) {
            $this->ensureSummary($args['summary'], $partition, (int) $this->javaProperties['summary.filter.stringValuePrefix']);

            foreach ((array) $this->testConfig['summary'] as $summary)
                $this->checkSummary($summary);
        }
        if ($this->needPartition) {
            $lpartitions = \ensureArray($this->testConfig['partition']);
            $lpartitions = $this->partitionsMustBeGenerated($lpartitions, $this->javaProperties['partition.id']);

            if (! empty($lpartitions)) {
                $this->ensurePartition($partition);
                $this->checkPartition($lpartitions, $this->javaProperties['partition.id']);
            }
        }
    }

    private function partitionsMustBeGenerated(array $lpartitions, string $partitionID): array
    {
        $ret = [];

        foreach ($lpartitions as $lpart) {
            $path = $lpart->filePath($this->ds, $partitionID);

            if (! \is_file($path))
                $ret[] = $lpart;
        }
        return $ret;
    }

    private function checkPartition(array $lpartitions, string $partitionID): void
    {
        foreach ($lpartitions as $lpart) {
            $path = $lpart->filePath($this->ds, $partitionID);

            if (! \is_file($path))
                throw new \Exception("The partition file $path must exists");
        }
    }

    private function ensurePartition($partition): void
    {
        $summArgs = [
            $this->ds,
            'cmd' => 'partition',
            'skip-existing' => true,
            'generate-dataset' => false,
            'clean-db' => false,
            'output' => \sys_get_temp_dir(),
            'plot' => false,
            'doonce' => true,
            'forget-results' => true,
            'Ppartition.id' => $this->javaProperties['partition.id']
        ];
        $doItParser = CmdArgs::default();
        $doItParser->parse($summArgs);
        $doIt = new OneTest($this->ds, $doItParser, $partition);
        $doIt->setDisplayHeader(false);
        $doIt->execute();
    }

    private function ensureSummary(string $summary, \Data\IPartition $partition, int $strValuePrefix): void
    {
        if (empty($summary))
            return;

        DoSummarize::summarize($this->ds, $partition, $summary, $strValuePrefix);
    }

    private function checkSummary(string $path): void
    {
        if (empty($type))
            return;

        if (! \is_file($path))
            throw new \Exception("Summary '$summaryPath' does not exists");
    }

    private function postCleanDb()
    {
        $args = $this->args;

        if ($args['post-clean-db'] || $args['clean-db'])
            $this->dropCollections($this->cleanDBGlob);
    }

    private function postProcess()
    {
        $args = $this->args;
        $config = $this->testConfig;

        $this->postCleanDb();

        if ($args['forget-results'] && \is_dir($config['bench.output.path']))
            \rrmdir($config['bench.output.path']);
        else
            \touch($config['bench.output.path'] . '/@end');
    }
}