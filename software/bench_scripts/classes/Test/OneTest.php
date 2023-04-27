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

    private int $preCleanDataSet = 0;

    private int $postCleanDataSet = 0;

    private array $javaProperties;

    public function __construct(\DataSet $ds, CmdArgs $cmdParser, \Data\IPartition ...$partitions)
    {
        parent::__construct($ds, $cmdParser, ...$partitions);

        $args = &$cmdParser['args'];
        $javaProperties = $cmdParser['javaProperties'];

        $this->doonce = $args['doonce'];

        if ($args['clean-ds'] === true)
            $args['pre-clean-ds'] = $args['post-clean-ds'] = true;
        elseif ($args['clean-ds'])
            $args['pre-clean-ds'] = $args['post-clean-ds'] = $args['clean-ds'];

        if ($args['pre-clean-ds'] === true)
            $this->preCleanDataSet = \Data\IJsonLoader::CLEAN_ALL;
        elseif ($args['pre-clean-ds'])
            $this->preCleanDataSet = $args['pre-clean-ds'];

        if ($args['post-clean-ds'] === true)
            $this->postCleanDataSet = \Data\IJsonLoader::CLEAN_ALL;
        elseif ($args['post-clean-ds'])
            $this->postCleanDataSet = $args['post-clean-ds'];

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
        elseif (($args['cmd'] == 'querying' && $javaProperties['querying.mode'] == 'summary_stats'))
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

        $this->testConfig = \makeConfig($this->dbImport, $this->ds, $partitions, $cmdParser);
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
            $this->preCleanDb();
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
        $args = $this->cmdParser['args'];
        $testConfig = $this->testConfig;
        $partition = $this->partitions[0];

        if ($this->needDatabase) {
            $this->preCleanDb();
            $collExists = $this->collectionsExists();

            if ($args['generate-dataset']) {

                if (! $collExists) {
                    $this->loadCollections();
                    $collExists = $this->collectionsExists();
                }
            }

            if (! $collExists)
                throw new \Exception("The collection treeforce.$this->ds must exists in the database");

            // Load the index
            if ($args['cmd'] === 'querying' && $partition->isLogical()) {
                $partitionId = $this->javaProperties['partition.id'];
                $this->loadIndex($partitionId);
            }
        }
        { // Summaries check
            if ($args['cmd'] === 'partition' && $partition->isLogical())
                $partitions = \ensureArray($partition->getPhysicalParent());
            else
                $partitions = \ensureArray($this->partitions);

            if ($this->needNativeSummary) {
                $this->ensurePartitionsSummary($args['toNative_summary'], $partitions, 0);
                $this->checkSummaries((array) $this->testConfig['toNative.summary']);
            }
            if ($this->needSummary) {
                $this->ensurePartitionsSummary($args['summary'], $partitions, (int) $this->javaProperties['summary.filter.stringValuePrefix']);
                $this->checkSummaries((array) $this->testConfig['summary']);
            }
        }
        if ($this->needPartition) {
            $lpartitions = \ensureArray($this->testConfig['partition']);
            $lpartitions = $this->partitionsMustBeGenerated($lpartitions, $this->javaProperties['partition.id']);

            if (! empty($lpartitions)) {
                $this->ensurePartitions($lpartitions);
                $this->checkPartitions($lpartitions, $this->javaProperties['partition.id']);
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

    private function checkPartitions(array $lpartitions, string $partitionID): void
    {
        foreach ($lpartitions as $lpart) {
            $path = $lpart->filePath($this->ds, $partitionID);

            if (! \is_file($path))
                throw new \Exception("The partition file $path must exists");
        }
    }

    private function ensurePartitions(array $partitions): void
    {
        foreach ($partitions as $partition)
            $this->ensurePartition($partition);

        // TODO: do not repeat \makeConfig twice : make a class MakeConfig
        $this->testConfig = \makeConfig($this->dbImport, $this->ds, $this->partitions, $this->cmdParser);
    }

    private function ensurePartition(\Data\LogicalPartition $partition): void
    {
        echo "\nGet partition: {$partition->getID()}\n";

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
            'documentstore' => $this->cmdParser['args']['documentstore'],
            'Ppartition.id' => $this->javaProperties['partition.id']
        ];
        $doItParser = CmdArgs::default();
        $doItParser->parse($summArgs);
        $doIt = new OneTest($this->ds, $doItParser, $partition);
        $doIt->setDisplayHeader(false);
        $doIt->execute();
    }

    private function ensurePartitionsSummary(string $summaryType, array $partitions, int $strValuePrefix): void
    {
        foreach ($partitions as $p)
            $this->ensureSummary($summaryType, $p, $strValuePrefix);
    }

    private function ensureSummary(string $summary, \Data\IPartition $partition, int $strValuePrefix): void
    {
        if (empty($summary))
            return;

        DoSummarize::summarize($this->dbImport, $this->ds, $partition, $summary, $strValuePrefix);
    }

    private function checkSummaries(array $paths): void
    {
        foreach ($paths as $p)
            $this->checkSummary($p);
    }

    private function checkSummary(string $path): void
    {
        if (empty($type))
            return;

        if (! \is_file($path))
            throw new \Exception("Summary '$summaryPath' does not exists");
    }

    private function preCleanDb()
    {
        $args = $this->cmdParser['args'];

        if ($args['pre-clean-db'] || $args['clean-db'])
            $this->dbImport->dropDataset($this->ds);

        if ($this->preCleanDataSet)
            $this->ds->getJsonLoader()->cleanFiles($this->preCleanDataSet);
    }

    private function postCleanDb()
    {
        $args = $this->cmdParser['args'];

        if ($args['post-clean-db'] || $args['clean-db'])
            $this->dbImport->dropDataset($this->ds);

        if ($this->postCleanDataSet)
            $this->ds->getJsonLoader()->cleanFiles($this->postCleanDataSet);
    }

    private function postProcess()
    {
        $args = $this->cmdParser['args'];
        $config = $this->testConfig;

        $this->postCleanDb();

        if ($args['forget-results'] && \is_dir($config['bench.output.path']))
            \rrmdir($config['bench.output.path']);
        else
            \touch($config['bench.output.path'] . '/@end');
    }
}