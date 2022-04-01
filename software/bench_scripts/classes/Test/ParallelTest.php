<?php
namespace Test;

final class ParallelTest extends AbstractTest
{

    private array $testConfig;

    private array $partitions;

    public function __construct(\DataSet $ds, array $partitions, CmdArgs $cmdParser)
    {
        parent::__construct($ds, \Data\NoPartitioning::noPartition(), $cmdParser);

        $parsed = $this->cmdParser->parsed();
        $args = &$parsed['args'];
        $javaProperties = $parsed['javaProperties'];

        $this->partitions = $partitions;
        $this->doonce = $args['doonce'];

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
        $this->testConfig = \makeConfig($ds, $partitions, $args, $javaProperties);
    }

    public function getTestConfig(): array
    {
        return $this->testConfig;
    }

    public function execute()
    {
        $header = \str_repeat('=', \strlen((string) $this->ds));
        echo "\n$header\nPARALLEL TEST\n$this->ds\n";

        if (! empty($this->testConfig['test.existing'])) {
            echo "Similar test already exists:\n";

            foreach ($this->testConfig['test.existing'] as $test)
                echo $test, "\n";

            return;
        }

        try {
            $bench = new \Benchmark($this->testConfig);
            if ($this->doonce)
                $bench->executeOnce();
            else
                $bench->doTheBenchmark();
        } catch (\Exception $e) {
            $this->errors[] = [
                'dataset' => $this->ds,
                'collection' => $this->collection,
                'exception' => $e
            ];
            \fwrite(STDERR, "<$this->ds>Exception:\n {$e->getMessage()}\n");
        }
    }
}